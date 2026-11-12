<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Service (« Département » sur les fiches).
 *
 * @property int $id
 * @property int $direction_id
 * @property string $name
 * @property int|null $head_id
 */
#[Fillable(['direction_id', 'name', 'head_id'])]
class Department extends Model
{
    /** @return BelongsTo<Direction, $this> */
    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class);
    }

    /** Chef de service. @return BelongsTo<User, $this> */
    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
