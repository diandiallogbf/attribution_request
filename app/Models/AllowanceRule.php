<?php

namespace App\Models;

use App\Enums\EmployeeCategory;
use App\Enums\PhoneType;
use App\ValueObjects\MoneyGnf;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Grille de dotation : ce à quoi une catégorie de collaborateur a droit.
 *
 * @property int $id
 * @property EmployeeCategory $employee_category
 * @property Collection<int, PhoneType> $allowed_phone_types
 * @property MoneyGnf $max_monthly_recharge_gnf
 * @property int $renewal_months
 * @property string|null $notes
 */
#[Fillable(['employee_category', 'allowed_phone_types', 'max_monthly_recharge_gnf', 'renewal_months', 'notes'])]
class AllowanceRule extends Model
{
    protected function casts(): array
    {
        return [
            'employee_category' => EmployeeCategory::class,
            'allowed_phone_types' => AsEnumCollection::of(PhoneType::class),
            'max_monthly_recharge_gnf' => MoneyGnf::class,
            'renewal_months' => 'integer',
        ];
    }

    public static function forCategory(?EmployeeCategory $category): ?self
    {
        return $category ? self::query()->where('employee_category', $category)->first() : null;
    }

    public function allowsPhoneType(PhoneType $type): bool
    {
        return $this->allowed_phone_types->contains($type);
    }

    public function allowsRecharge(MoneyGnf $amount): bool
    {
        return ! $amount->isGreaterThan($this->max_monthly_recharge_gnf);
    }
}
