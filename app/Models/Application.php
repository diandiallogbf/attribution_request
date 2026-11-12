<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Application métier (Maximo, Amplitude, Iris…). Sa direction propriétaire valide les accès.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int|null $owner_direction_id
 * @property bool $requires_business_approval
 * @property bool $is_active
 * @property int $sort_order
 * @property-read Direction|null $ownerDirection
 */
#[Fillable(['code', 'name', 'description', 'owner_direction_id', 'requires_business_approval', 'is_active', 'sort_order'])]
class Application extends Model
{
    protected function casts(): array
    {
        return [
            'requires_business_approval' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Direction, $this> */
    public function ownerDirection(): BelongsTo
    {
        return $this->belongsTo(Direction::class, 'owner_direction_id');
    }

    /** Un directeur doit valider l'accès à cette application. */
    public function needsBusinessApproval(): bool
    {
        return $this->requires_business_approval && $this->owner_direction_id !== null;
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
