<?php

use App\Enums\EmployeeCategory;
use App\Models\User;
use Database\Seeders\OrganizationSeeder;
use Database\Seeders\ReferentialSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/** Référentiels minimaux (rôles, organisation, grille, montants, catalogue). */
function seedReferentials(): void
{
    (new RoleSeeder)->run();
    (new OrganizationSeeder)->run();
    (new ReferentialSeeder)->run();
}

/**
 * Une équipe type : un manager et son collaborateur.
 *
 * @return array{manager: User, employee: User}
 */
function team(EmployeeCategory $category = EmployeeCategory::Supervisor): array
{
    $manager = User::factory()->category(EmployeeCategory::Executive)->create();
    $employee = User::factory()->category($category)->managedBy($manager)->create();

    return ['manager' => $manager, 'employee' => $employee];
}
