<?php

namespace Database\Seeders;

use App\Enums\EmployeeCategory;
use App\Enums\PhoneType;
use App\Models\AllowanceRule;
use App\Models\Application;
use App\Models\DeviceModel;
use App\Models\Direction;
use App\Models\Operator;
use App\Models\RechargeAmount;
use Illuminate\Database\Seeder;

class ReferentialSeeder extends Seeder
{
    /**
     * Applications de la fiche F-0008. Directions propriétaires d'après les notes DSI
     * (DT : Maximo, DRH : Amplitude, DAF : Iris, DEX : Spot) ; les autres sont PROVISOIRES
     * et modifiables dans le back-office (Référentiels → Applications).
     */
    private function seedApplications(): void
    {
        $directions = Direction::query()->pluck('id', 'code');

        $applications = [
            // code, nom, description, direction, validation métier
            ['APACHE', 'Apache', 'Serveur web / intranet', null, false],
            ['AMPLITUDE', 'Amplitude', 'Paie et gestion RH', 'DRH', true],
            ['OSCAR', 'Oscar', 'Comptabilité', 'DAF', true],
            ['SPOT', 'Spot', 'Exploitation terminal', 'DEX', true],
            ['ALIS', 'ALIS', 'Logistique', 'DILS', true],
            ['SYDONIA', 'Sydonia', 'Dédouanement (SYDONIA World)', 'DSHIP', true],
            ['IPAKI', 'Ipaki', 'Gestion des stocks', 'DILS', true],
            ['IRIS', 'Iris / Neptune', 'Finance et achats', 'DAF', true],
            ['MAXIMO', 'Maximo', 'Maintenance des équipements', 'DT', true],
            ['SERVAL', 'Serval', 'Shipping', 'DSHIP', true],
        ];

        foreach ($applications as $order => [$code, $name, $description, $direction, $businessApproval]) {
            Application::updateOrCreate(['code' => $code], [
                'name' => $name,
                'description' => $description,
                'owner_direction_id' => $direction ? $directions[$direction] ?? null : null,
                'requires_business_approval' => $businessApproval,
                'is_active' => true,
                'sort_order' => $order,
            ]);
        }
    }

    public function run(): void
    {
        Operator::updateOrCreate(['code' => 'OGN'], ['name' => 'Orange Guinée']);

        $this->seedApplications();

        // Montants de la fiche AGL-GIN-DSI-F-0015 (recharge mensuelle).
        foreach ([10_000, 25_000, 50_000, 100_000, 200_000, 250_000, 300_000, 350_000, 400_000, 450_000,
            500_000, 600_000, 800_000, 1_000_000, 1_500_000, 2_000_000, 4_000_000] as $amount) {
            RechargeAmount::updateOrCreate(['amount_gnf' => $amount], ['is_active' => true]);
        }

        // ⚠ Grille de dotation PROVISOIRE : valeurs à remplacer par la grille officielle de la DSI
        // (modifiable ensuite dans le back-office, sans développeur).
        $grid = [
            EmployeeCategory::Director->value => [[PhoneType::Iphone, PhoneType::SamsungAndroid, PhoneType::Tablet], 2_000_000, 24],
            EmployeeCategory::Executive->value => [[PhoneType::SamsungAndroid, PhoneType::Tablet], 600_000, 24],
            EmployeeCategory::Supervisor->value => [[PhoneType::SamsungAndroid, PhoneType::SamsungBasic], 250_000, 36],
            EmployeeCategory::Employee->value => [[PhoneType::SamsungBasic], 100_000, 36],
        ];

        foreach ($grid as $category => [$types, $maxRecharge, $renewalMonths]) {
            AllowanceRule::updateOrCreate(['employee_category' => $category], [
                'allowed_phone_types' => $types,
                'max_monthly_recharge_gnf' => $maxRecharge,
                'renewal_months' => $renewalMonths,
                'notes' => 'Valeurs provisoires -à valider par la DSI.',
            ]);
        }

        $catalog = [
            ['Samsung', 'Guru Music 2', PhoneType::SamsungBasic, null, 350_000],
            ['Samsung', 'Galaxy A16', PhoneType::SamsungAndroid, 128, 2_100_000],
            ['Samsung', 'Galaxy A56', PhoneType::SamsungAndroid, 256, 4_500_000],
            ['Apple', 'iPhone 16', PhoneType::Iphone, 128, 9_800_000],
            ['Samsung', 'Galaxy Tab A9+', PhoneType::Tablet, 64, 2_600_000],
        ];

        foreach ($catalog as [$brand, $name, $type, $storage, $price]) {
            DeviceModel::updateOrCreate(
                ['brand' => $brand, 'name' => $name, 'storage_gb' => $storage],
                ['phone_type' => $type, 'unit_price_gnf' => $price, 'is_active' => true],
            );
        }
    }
}
