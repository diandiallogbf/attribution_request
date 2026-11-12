<?php

namespace App\Services\Finance;

use App\Enums\SimLineStatus;
use App\Models\Direction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SimLine;
use App\ValueObjects\MoneyGnf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Dépenses d'attribution pour la Finance :
 *  - matériel et puces commandés (montant de la facture s'il est saisi, sinon celui du bon de commande) ;
 *  - recharges mensuelles des lignes actives de la flotte (engagement en cours).
 * Volumes modestes (quelques centaines de commandes par an) : agrégation en PHP, portable SQLite / MySQL / SQL Server.
 */
final class SpendingReport
{
    public const UNALLOCATED = 'Non affecté';

    /** @var array<int, Collection<int, Order>> */
    private array $orders = [];

    /** @var Collection<int, SimLine>|null */
    private ?Collection $lines = null;

    /** @return Collection<int, Order> */
    public function orders(int $year): Collection
    {
        return $this->orders[$year] ??= Order::query()
            ->spending()
            ->whereBetween('ordered_at', [Carbon::create($year)->startOfYear(), Carbon::create($year)->endOfYear()])
            ->with(['items.serviceRequest', 'items.deviceModel', 'operator', 'orderedBy'])
            ->orderBy('ordered_at')
            ->get();
    }

    /** Lignes actives de la flotte avec leur titulaire et sa direction. @return Collection<int, SimLine> */
    public function rechargeLines(): Collection
    {
        return $this->lines ??= SimLine::query()
            ->where('status', SimLineStatus::Active)
            ->where('is_fleet', true)
            ->with(['operator', 'currentAssignment.user.department.direction'])
            ->orderBy('msisdn')
            ->get();
    }

    public function monthlyRechargeCommitment(): MoneyGnf
    {
        return MoneyGnf::sum($this->rechargeLines()->pluck('monthly_recharge_gnf'));
    }

    /**
     * @return array{spent: MoneyGnf, spentThisMonth: MoneyGnf, orders: int, toInvoice: array{count: int, amount: MoneyGnf}, unpaid: array{count: int, amount: MoneyGnf}, monthlyRecharge: MoneyGnf, lines: int}
     */
    public function totals(int $year): array
    {
        $orders = $this->orders($year);
        $toInvoice = $orders->filter(fn (Order $order) => $order->invoice_reference === null && $order->paid_at === null);
        $unpaid = $orders->filter(fn (Order $order) => $order->invoice_reference !== null && $order->paid_at === null);

        return [
            'spent' => MoneyGnf::sum($orders->map->spentAmount()),
            'spentThisMonth' => MoneyGnf::sum($orders
                ->filter(fn (Order $order) => $order->ordered_at?->isSameMonth(now()) ?? false)
                ->map->spentAmount()),
            'orders' => $orders->count(),
            'toInvoice' => ['count' => $toInvoice->count(), 'amount' => MoneyGnf::sum($toInvoice->map->spentAmount())],
            'unpaid' => ['count' => $unpaid->count(), 'amount' => MoneyGnf::sum($unpaid->map->spentAmount())],
            'monthlyRecharge' => $this->monthlyRechargeCommitment(),
            'lines' => $this->rechargeLines()->count(),
        ];
    }

    /**
     * Synthèse mois par mois (date de commande).
     *
     * @return list<array{month: int, label: string, orders: int, ordered: MoneyGnf, invoiced: MoneyGnf, paid: MoneyGnf, spent: MoneyGnf}>
     */
    public function monthly(int $year): array
    {
        $byMonth = $this->orders($year)->groupBy(fn (Order $order) => $order->ordered_at->month);

        return collect(range(1, 12))->map(function (int $month) use ($year, $byMonth): array {
            /** @var Collection<int, Order> $orders */
            $orders = $byMonth->get($month, new Collection);

            return [
                'month' => $month,
                'label' => ucfirst(Carbon::create($year, $month)->translatedFormat('F Y')),
                'orders' => $orders->count(),
                'ordered' => MoneyGnf::sum($orders->pluck('total_gnf')),
                'invoiced' => MoneyGnf::sum($orders->pluck('invoice_amount_gnf')),
                'paid' => MoneyGnf::sum($orders->filter(fn (Order $order) => $order->paid_at !== null)->map->spentAmount()),
                'spent' => MoneyGnf::sum($orders->map->spentAmount()),
            ];
        })->all();
    }

    /**
     * Ventilation par direction du bénéficiaire : matériel de l'année + recharges mensuelles en cours.
     *
     * @return list<array{code: string, name: string, equipment: MoneyGnf, lines: int, monthlyRecharge: MoneyGnf}>
     */
    public function byDirection(int $year): array
    {
        $names = Direction::query()->pluck('name', 'code');
        $rows = [];

        $row = function (string $code) use (&$rows, $names): void {
            $rows[$code] ??= [
                'code' => $code,
                'name' => $names[$code] ?? ($code === self::UNALLOCATED ? __('Stock / non affecté') : $code),
                'equipment' => MoneyGnf::zero(),
                'lines' => 0,
                'monthlyRecharge' => MoneyGnf::zero(),
            ];
        };

        foreach ($this->orders($year) as $order) {
            foreach ($this->allocate($order) as $code => $amount) {
                $row($code);
                $rows[$code]['equipment'] = $rows[$code]['equipment']->add($amount);
            }
        }

        foreach ($this->rechargeLines() as $line) {
            $code = $this->lineDirection($line);
            $row($code);
            $rows[$code]['lines']++;
            $rows[$code]['monthlyRecharge'] = $rows[$code]['monthlyRecharge']->add($line->monthly_recharge_gnf ?? MoneyGnf::zero());
        }

        return collect($rows)
            ->sortByDesc(fn (array $row) => $row['equipment']->amount + 12 * $row['monthlyRecharge']->amount)
            ->values()
            ->all();
    }

    /** Direction du bénéficiaire telle qu'elle était à la soumission (photo figée de la demande). */
    public function itemDirection(OrderItem $item): string
    {
        return $item->serviceRequest?->beneficiary_snapshot['direction'] ?? self::UNALLOCATED;
    }

    public function lineDirection(SimLine $line): string
    {
        return $line->currentAssignment?->user?->department?->direction?->code ?? self::UNALLOCATED;
    }

    /**
     * Répartit le montant d'une commande entre ses lignes, au prorata des quantités.
     *
     * @return array<string, MoneyGnf>
     */
    private function allocate(Order $order): array
    {
        $amount = $order->spentAmount()->amount;
        $items = $order->items;
        $quantity = max(1, (int) $items->sum('quantity'));

        if ($items->isEmpty()) {
            return [self::UNALLOCATED => MoneyGnf::from($amount)];
        }

        $shares = [];
        $allocated = 0;

        foreach ($items->values() as $index => $item) {
            $share = $index === $items->count() - 1 ? $amount - $allocated : intdiv($amount * $item->quantity, $quantity);
            $allocated += $share;
            $code = $this->itemDirection($item);
            $shares[$code] = ($shares[$code] ?? MoneyGnf::zero())->add(MoneyGnf::from($share));
        }

        return $shares;
    }
}
