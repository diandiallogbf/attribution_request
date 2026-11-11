<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\ValueObjects\MoneyGnf;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Bon de commande (appareils) ou demande opérateur (SIM).
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
 */
#[Fillable([
    'reference', 'type', 'status', 'supplier', 'operator_id', 'ordered_by_id',
    'ordered_at', 'expected_at', 'received_at', 'total_gnf', 'notes',
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

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
