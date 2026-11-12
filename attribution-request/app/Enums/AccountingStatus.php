<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Models\Order;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Builder;

/** Avancement comptable d'une commande, déduit des saisies de la Finance (jamais stocké). */
enum AccountingStatus: string implements HasColor, HasLabel
{
    use EnumHelpers;

    case ToInvoice = 'to_invoice';
    case Invoiced = 'invoiced';
    case Paid = 'paid';

    public function getLabel(): string
    {
        return match ($this) {
            self::ToInvoice => __('À facturer'),
            self::Invoiced => __('Facturée'),
            self::Paid => __('Réglée'),
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ToInvoice => 'gray',
            self::Invoiced => 'warning',
            self::Paid => 'success',
        };
    }

    /** @param  Builder<Order>  $query */
    public function applyTo(Builder $query): void
    {
        match ($this) {
            self::ToInvoice => $query->whereNull('invoice_reference')->whereNull('paid_at'),
            self::Invoiced => $query->whereNotNull('invoice_reference')->whereNull('paid_at'),
            self::Paid => $query->whereNotNull('paid_at'),
        };
    }
}
