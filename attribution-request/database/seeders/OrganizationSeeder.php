<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Direction;
use App\Models\Site;
use Illuminate\Database\Seeder;

/**
 * Sites et directions cités sur les fiches. Les services sont un point de départ :
 * l'organigramme réel sera importé (CSV) puis synchronisé depuis Microsoft Entra ID.
 */
class OrganizationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'AGL' => 'Africa Global Logistics',
            'CKT' => 'Conakry Terminal',
        ] as $code => $name) {
            Site::updateOrCreate(['code' => $code], ['name' => $name]);
        }

        $directions = [
            'DG' => ['Direction Générale', ['Secrétariat Général']],
            'DSI' => ['Direction des Systèmes d\'Information', ['Infrastructure & Réseaux', 'Applications', 'Support utilisateurs']],
            'DRH' => ['Direction des Ressources Humaines', ['Administration du personnel', 'Formation']],
            'DAF' => ['Direction Administrative et Financière', ['Comptabilité', 'Achats', 'Trésorerie']],
            'DT' => ['Direction Technique', ['Maintenance', 'Équipements portuaires']],
            'DEX' => ['Direction de l\'Exploitation', ['Opérations terminal', 'Planification']],
            'DILS' => ['Direction Logistique', ['Entreposage', 'Transport']],
            'DSHIP' => ['Direction Shipping', ['Agence maritime']],
        ];

        foreach ($directions as $code => [$name, $departments]) {
            $direction = Direction::updateOrCreate(['code' => $code], ['name' => $name]);

            foreach ($departments as $department) {
                Department::firstOrCreate(['direction_id' => $direction->id, 'name' => $department]);
            }
        }
    }
}
