<?php

namespace App\Models;

use App\Enums\AccountingStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\ValueObjects\MoneyGnf;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Bon de commande (appareils) ou demande opérateur (SIM), avec son suivi comptable.
 *
 * @property int $id
 * @property string|null $reference
 * @property OrderType $type
 * @property OrderStatus $status
 * @property string|null $supplier
 * @property int|null $operator_id
 * @property int|null $ordered_by_id
 * @property Carbon|null $ordered_at
 * @property Carbon|null $expected_at
 * @property Carbon|null $received_at
 * @property MoneyGnf|null $total_gnf
 * @property string|null $notes
 * @property string|null $invoice_reference
 * @property Carbon|null $invoiced_at
 * @property MoneyGnf|null $invoice_amount_gnf
 * @property string|null $cost_center
 * @property Carbon|null $paid_at
 * @property string|null $finance_notes
 * @property int|null $accounted_by_id
 * @property Carbon|null $accounted_at
 */
#[Fillable([
    'reference', 'type', 'status', 'supplier', 'operator_id', 'ordered_by_id',
    'ordered_at', 'expected_at', 'received_at', 'total_gnf', 'notes',
    'invoice_reference', 'invoiced_at', 'invoice_amount_gnf', 'cost_center', 'paid_at', 'finance_notes',
    'accounted_by_id', 'accounted_at',
])]
class Order extends Model
{
    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrderType::class,
            'status' => OrderStatus::class,
            'ordered_at' => 'datetime',
            'expected_at' => 'date',
            'received_at' => 'datetime',
            'total_gnf' => MoneyGnf::class,
            'invoiced_at' => 'date',
            'invoice_amount_gnf' => MoneyGnf::class,
            'paid_at' => 'date',
            'accounted_at' => 'datetime',
        ];
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return BelongsTo<Operator, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    /** @return BelongsTo<User, $this> */
    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function accountedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accounted_by_id');
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Commandes qui pèsent dans les dépenses (tout sauf brouillon et annulée). @param  Builder<self>  $query */
    public function scopeSpending(Builder $query): void
    {
        $query->whereNotIn('status', [OrderStatus::Draft, OrderStatus::Cancelled]);
    }

    /** Montant retenu en comptabilité : la facture fait foi, sinon le montant du bon de commande. */
    public function spentAmount(): MoneyGnf
    {
        return $this->invoice_amount_gnf ?? $this->total_gnf ?? MoneyGnf::zero();
    }

    public function accountingStatus(): AccountingStatus
    {
        return match (true) {
            $this->paid_at !== null => AccountingStatus::Paid,
            $this->invoice_reference !== null => AccountingStatus::Invoiced,
            default => AccountingStatus::ToInvoice,
        };
    }
}
