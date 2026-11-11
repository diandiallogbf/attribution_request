<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Référentiels : nécessaires en production. Démo : uniquement en local / recette.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            OrganizationSeeder::class,
            ReferentialSeeder::class,
        ]);

        if (app()->environment(['local', 'testing', 'staging'])) {
            $this->call(DemoSeeder::class);
        }
    }
}
