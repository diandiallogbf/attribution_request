<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 */
#[Fillable(['code', 'name', 'is_active'])]
class Operator extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<SimLine, $this> */
    public function simLines(): HasMany
    {
        return $this->hasMany(SimLine::class);
    }
}
