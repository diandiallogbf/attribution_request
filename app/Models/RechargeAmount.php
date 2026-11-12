<?php

namespace App\Models;

use App\ValueObjects\MoneyGnf;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Montants de recharge mensuelle proposés sur la fiche.
 *
 * @property int $id
 * @property MoneyGnf $amount_gnf
 * @property bool $is_active
 */
#[Fillable(['amount_gnf', 'is_active'])]
class RechargeAmount extends Model
{
    protected function casts(): array
    {
        return [
            'amount_gnf' => MoneyGnf::class,
            'is_active' => 'boolean',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('amount_gnf');
    }
}
