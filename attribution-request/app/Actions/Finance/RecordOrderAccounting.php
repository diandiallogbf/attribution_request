<?php

namespace App\Actions\Finance;

use App\Actions\Fulfilment\Concerns\EnsuresRole;
use App\Data\OrderAccountingData;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Exceptions\Domain\AccountingException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Écriture comptable d'une commande : facture fournisseur, imputation analytique, règlement.
 * La facture fait ensuite foi dans les tableaux de dépenses (à la place du montant du bon de commande).
 */
final class RecordOrderAccounting
{
    use EnsuresRole;

    public function handle(Order $order, User $actor, OrderAccountingData $data): Order
    {
        $this->ensureRole($actor, Role::accounting(), __('saisir une écriture comptable'));

        $invoiceReference = filled($data->invoiceReference) ? trim($data->invoiceReference) : null;

        if ($data->paidAt !== null && ($invoiceReference === null || $data->invoiceAmount === null)) {
            throw AccountingException::paymentWithoutInvoice();
        }

        if ($data->invoicedAt?->isAfter(today()->endOfDay())) {
            throw AccountingException::futureDate(__('facture'));
        }

        if ($data->paidAt?->isAfter(today()->endOfDay())) {
            throw AccountingException::futureDate(__('règlement'));
        }

        if ($data->paidAt !== null && $data->invoicedAt !== null && $data->paidAt->toDateString() < $data->invoicedAt->toDateString()) {
            throw AccountingException::paymentBeforeInvoice();
        }

        return DB::transaction(function () use ($order, $actor, $data, $invoiceReference): Order {
            $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($order->status === OrderStatus::Cancelled) {
                throw AccountingException::cancelledOrder($order->reference ?? '#'.$order->id);
            }

            $order->fill([
                'invoice_reference' => $invoiceReference,
                'invoiced_at' => $data->invoicedAt,
                'invoice_amount_gnf' => $data->invoiceAmount,
                'cost_center' => filled($data->costCenter) ? trim($data->costCenter) : null,
                'paid_at' => $data->paidAt,
                'finance_notes' => filled($data->notes) ? trim($data->notes) : null,
                'accounted_by_id' => $actor->id,
                'accounted_at' => now(),
            ])->save();

            return $order;
        });
    }
}
