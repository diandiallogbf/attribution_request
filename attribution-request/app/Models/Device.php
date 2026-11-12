<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use App\ValueObjects\Imei;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Appareil physique du parc (téléphone, tablette…).
 *
 * @property int $id
 * @property int $device_model_id
 * @property Imei|null $imei
 * @property string|null $serial_number
 * @property DeviceStatus $status
 * @property int|null $order_item_id
 * @property Carbon|null $acquired_at
 * @property string|null $notes
 * @property-read DeviceModel $model
 * @property-read string $label
 */
#[Fillable(['device_model_id', 'imei', 'serial_number', 'status', 'order_item_id', 'acquired_at', 'notes'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'imei' => Imei::class,
            'status' => DeviceStatus::class,
            'acquired_at' => 'date',
        ];
    }

    /** @return BelongsTo<DeviceModel, $this> */
    public function model(): BelongsTo
    {
        return $this->belongsTo(DeviceModel::class, 'device_model_id');
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return MorphMany<Assignment, $this> */
    public function assignments(): MorphMany
    {
        return $this->morphMany(Assignment::class, 'assignable');
    }

    /** @return MorphOne<Assignment, $this> */
    public function currentAssignment(): MorphOne
    {
        return $this->morphOne(Assignment::class, 'assignable')->whereNull('returned_at')->latestOfMany('assigned_at');
    }

    /** « Samsung Galaxy A16 · 128 Go — IMEI 35-…-1 » */
    protected function label(): Attribute
    {
        return Attribute::get(fn (): string => $this->model->label
            .($this->imei ? ' — IMEI '.$this->imei->formatted() : ($this->serial_number ? ' — S/N '.$this->serial_number : '')));
    }
}
