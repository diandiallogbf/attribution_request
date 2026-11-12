<?php

namespace App\Models;

use App\Enums\PhoneType;
use App\ValueObjects\MoneyGnf;
use Database\Factories\DeviceModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catalogue des modèles d'appareils proposés par la DSI.
 *
 * @property int $id
 * @property string $brand
 * @property string $name
 * @property PhoneType $phone_type
 * @property int|null $storage_gb
 * @property MoneyGnf|null $unit_price_gnf
 * @property bool $is_active
 * @property-read string $label
 */
#[Fillable(['brand', 'name', 'phone_type', 'storage_gb', 'unit_price_gnf', 'is_active'])]
class DeviceModel extends Model
{
    /** @use HasFactory<DeviceModelFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'phone_type' => PhoneType::class,
            'unit_price_gnf' => MoneyGnf::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Device, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /** « Samsung Galaxy A16 · 128 Go » */
    protected function label(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->brand} {$this->name}")
            .($this->storage_gb ? " · {$this->storage_gb} Go" : ''));
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
