<?php

namespace App\Services\Finance;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SimLine;
use App\Support\Spreadsheet\XlsxWorkbook;
use App\ValueObjects\MoneyGnf;
use Illuminate\Support\Carbon;

/** Feuilles Excel pour la Finance : mêmes colonnes partout (tableau de bord, commandes, recharges). */
final readonly class FinanceExports
{
    /** Relations lues par la feuille « Commandes » (à précharger sur la requête exportée). */
    public const ORDER_RELATIONS = ['items.serviceRequest', 'items.deviceModel', 'operator', 'orderedBy'];

    /** Relations lues par la feuille « Recharges ». */
    public const LINE_RELATIONS = ['operator', 'currentAssignment.user.department.direction'];

    public function __construct(private SpendingReport $report) {}

    /** Classeur complet d'un exercice : synthèse, ventilation, détail des commandes et des recharges. */
    public function yearWorkbook(int $year): XlsxWorkbook
    {
        $workbook = XlsxWorkbook::make()
            ->sheet("Synthèse {$year}", ['Mois', 'Commandes', 'Bons de commande', 'Facturé', 'Réglé', 'Dépense retenue'], array_map(
                fn (array $month): array => [$month['label'], $month['orders'], $month['ordered'], $month['invoiced'], $month['paid'], $month['spent']],
                $this->report->monthly($year),
            ), total: true)
            ->sheet('Par direction', ['Code', 'Direction', "Matériel {$year}", 'Lignes actives', 'Recharges / mois', 'Recharges / an (projection)'], array_map(
                fn (array $row): array => [$row['code'], $row['name'], $row['equipment'], $row['lines'], $row['monthlyRecharge'], $row['monthlyRecharge']->times(12)],
                $this->report->byDirection($year),
            ), total: true);

        $this->orders($workbook, $this->report->orders($year), "Commandes {$year}");
        $this->recharges($workbook, $this->report->rechargeLines(), 'Recharges '.ucfirst(now()->translatedFormat('F Y')));

        return $workbook;
    }

    /** @param  iterable<Order>  $orders */
    public function orders(XlsxWorkbook $workbook, iterable $orders, string $sheet = 'Commandes'): XlsxWorkbook
    {
        $rows = (function () use ($orders) {
            foreach ($orders as $order) {
                yield [
                    $order->reference,
                    $order->type->getLabel(),
                    $order->status->getLabel(),
                    $order->supplier ?? $order->operator?->name,
                    $order->items->map(fn (OrderItem $item) => $item->quantity.' × '.($item->deviceModel?->label ?? $item->description))->join(' ; '),
                    $order->items->map(fn (OrderItem $item) => $item->serviceRequest?->displayReference())->filter()->unique()->join(' ; '),
                    $order->items->map(fn (OrderItem $item) => $this->report->itemDirection($item))->unique()->join(' ; '),
                    $order->ordered_at,
                    $order->received_at,
                    $order->total_gnf,
                    $order->invoice_reference,
                    $order->invoiced_at,
                    $order->invoice_amount_gnf,
                    $order->spentAmount(),
                    $order->cost_center,
                    $order->paid_at,
                    $order->accountingStatus()->getLabel(),
                    $order->orderedBy?->name,
                ];
            }
        })();

        return $workbook->sheet($sheet, [
            'N° BC / réf. opérateur', 'Type', 'Statut', 'Fournisseur', 'Contenu', 'N° de fiche', 'Direction',
            'Commandée le', 'Reçue le', 'Montant BC', 'N° facture', 'Date facture', 'Montant facture',
            'Dépense retenue', 'Imputation', 'Réglée le', 'Statut comptable', 'Commandée par',
        ], $rows, total: true);
    }

    /** @param  iterable<SimLine>  $lines */
    public function recharges(XlsxWorkbook $workbook, iterable $lines, string $sheet = 'Recharges'): XlsxWorkbook
    {
        $rows = (function () use ($lines) {
            foreach ($lines as $line) {
                $user = $line->currentAssignment?->user;

                yield [
                    $line->msisdn->international(),
                    $line->operator?->name,
                    $line->plan_type->getLabel(),
                    $user?->matricule?->toStorage(),
                    $user?->name ?? 'Non affectée',
                    $user?->department?->name,
                    $this->report->lineDirection($line),
                    $line->monthly_recharge_gnf ?? MoneyGnf::zero(),
                    ($line->monthly_recharge_gnf ?? MoneyGnf::zero())->times(12),
                    $line->activated_at,
                ];
            }
        })();

        return $workbook->sheet($sheet, [
            'Numéro', 'Opérateur', 'Forfait', 'Matricule', 'Titulaire', 'Service', 'Direction',
            'Recharge mensuelle', 'Projection annuelle', 'Active depuis',
        ], $rows, total: true);
    }

    public static function fileName(string $subject): string
    {
        return 'AGL-DSI-'.$subject.'-'.Carbon::now()->format('Ymd-Hi').'.xlsx';
    }
}
