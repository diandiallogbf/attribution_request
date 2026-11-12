<?php

namespace Database\Seeders;

use App\Enums\DeviceStatus;
use App\Enums\EmployeeCategory;
use App\Enums\LineOption;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PlanType;
use App\Enums\Role;
use App\Enums\SimLineStatus;
use App\Models\Assignment;
use App\Models\Department;
use App\Models\Device;
use App\Models\DeviceModel;
use App\Models\Direction;
use App\Models\Operator;
use App\Models\Order;
use App\Models\SimLine;
use App\Models\Site;
use App\Models\User;
use App\Support\Luhn;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Jeu de démonstration (local / recette) : un compte par rôle du processus.
 * Mot de passe commun : « password ».
 */
class DemoSeeder extends Seeder
{
    private string $password;

    private int $matricule = 1001;

    public function run(): void
    {
        // Idempotent : relancer `db:seed` ne doit pas dupliquer le parc de démonstration.
        if (User::query()->where('email', 'admin@agl.test')->exists()) {
            $this->command?->info('Jeu de démonstration déjà présent : ignoré (utilisez migrate:fresh --seed pour repartir de zéro).');

            return;
        }

        $this->password = Hash::make('password');

        $agl = Site::where('code', 'AGL')->firstOrFail();
        $dept = fn (string $direction, string $name) => Department::where('name', $name)
            ->whereHas('direction', fn ($q) => $q->where('code', $direction))
            ->firstOrFail();

        // DSI
        $dsi = $this->user('Mamadou', 'Bah', 'Directeur des Systèmes d\'Information', EmployeeCategory::Director, $dept('DSI', 'Infrastructure & Réseaux'), $agl, roles: [Role::Dsi]);
        Direction::where('code', 'DSI')->update(['director_id' => $dsi->id]);

        $itManager = $this->user('Ibrahima', 'Camara', 'Responsable IT', EmployeeCategory::Executive, $dept('DSI', 'Support utilisateurs'), $agl, manager: $dsi, roles: [Role::ItManager], head: true);
        $this->user('Ousmane', 'Sylla', 'Technicien support', EmployeeCategory::Supervisor, $dept('DSI', 'Support utilisateurs'), $agl, manager: $itManager, roles: [Role::ItAgent]);
        $this->user('Kadiatou', 'Touré', 'Assistante de direction', EmployeeCategory::Employee, $dept('DSI', 'Infrastructure & Réseaux'), $agl, manager: $dsi, roles: [Role::Assistant]);
        $this->user('Admin', 'Système', 'Administrateur applicatif', EmployeeCategory::Executive, $dept('DSI', 'Applications'), $agl, manager: $dsi, roles: [Role::Admin], email: 'admin@agl.test');

        // Exploitation : un directeur, une cheffe de service, deux collaborateurs
        $ckt = Site::where('code', 'CKT')->firstOrFail();
        $dex = $this->user('Sékou', 'Keïta', 'Directeur de l\'Exploitation', EmployeeCategory::Director, $dept('DEX', 'Opérations terminal'), $ckt, roles: [Role::Director]);
        Direction::where('code', 'DEX')->update(['director_id' => $dex->id]);

        $manager = $this->user('Fatoumata', 'Diallo', 'Cheffe de service Opérations', EmployeeCategory::Executive, $dept('DEX', 'Opérations terminal'), $ckt, manager: $dex, head: true);
        $aissatou = $this->user('Aïssatou', 'Barry', 'Superviseure de quai', EmployeeCategory::Supervisor, $dept('DEX', 'Opérations terminal'), $ckt, manager: $manager);
        $mariama = $this->user('Mariama', 'Sow', 'Agente de pointage', EmployeeCategory::Employee, $dept('DEX', 'Opérations terminal'), $ckt, manager: $manager);

        // Directeurs métier : valident les accès aux applications de leur direction (fiche profil).
        foreach ([
            ['DT', 'Abdoulaye', 'Soumah', 'Directeur Technique', 'Maintenance'],
            ['DAF', 'Hawa', 'Kourouma', 'Directrice Administrative et Financière', 'Comptabilité'],
            ['DRH', 'Mariam', 'Bangoura', 'Directrice des Ressources Humaines', 'Administration du personnel'],
            ['DILS', 'Thierno', 'Baldé', 'Directeur Logistique', 'Entreposage'],
            ['DSHIP', 'Lansana', 'Fofana', 'Directeur Shipping', 'Agence maritime'],
        ] as [$code, $first, $last, $title, $department]) {
            $director = $this->user($first, $last, $title, EmployeeCategory::Director, $dept($code, $department), $agl, roles: [Role::Director]);
            Direction::where('code', $code)->update(['director_id' => $director->id]);
        }

        // Finance : suit les dépenses d'attribution, saisit factures et règlements.
        $this->user('Aminata', 'Condé', 'Comptable fournisseurs', EmployeeCategory::Executive, $dept('DAF', 'Comptabilité'), $agl, roles: [Role::Finance]);

        // Parc : équipements déjà attribués + stock disponible
        $operator = Operator::where('code', 'OGN')->firstOrFail();
        $model = fn (string $name) => DeviceModel::where('name', $name)->firstOrFail();

        $this->assign($aissatou, $this->device($model('Galaxy A16'), DeviceStatus::Assigned), $itManager, now()->subMonths(30));
        $this->assign($aissatou, $this->line($operator, '622104578', PlanType::Prepaid, [LineOption::NationalCalls], 250_000), $itManager, now()->subMonths(30));
        $this->assign($mariama, $this->device($model('Guru Music 2'), DeviceStatus::Assigned), $itManager, now()->subMonths(10));
        $this->assign($mariama, $this->line($operator, '628336120', PlanType::Prepaid, [LineOption::NationalCalls], 50_000), $itManager, now()->subMonths(10));
        $this->assign($manager, $this->device($model('Galaxy A56'), DeviceStatus::Assigned), $itManager, now()->subMonths(14));
        $this->assign($manager, $this->line($operator, '621907744', PlanType::Postpaid, [LineOption::NationalCalls, LineOption::InternationalCalls], 600_000), $itManager, now()->subMonths(14));

        foreach (['Guru Music 2', 'Guru Music 2', 'Galaxy A16', 'Galaxy A16', 'Galaxy A16', 'iPhone 16'] as $name) {
            $this->device($model($name), DeviceStatus::InStock);
        }

        // Historique d'achats (réapprovisionnement du stock) à différents stades comptables.
        $this->order('BC-2609-014', 'Orange Business', $model('Galaxy A16'), 3, 2_100_000, now()->subMonths(2), invoice: ['FAC-OB-2209', 6_300_000, 'paid'], by: $itManager);
        $this->order('BC-2610-021', 'Orange Business', $model('iPhone 16'), 1, 9_800_000, now()->subMonth(), invoice: ['FAC-OB-2341', 9_800_000], by: $itManager);
        $this->order('BC-2611-003', 'Guinée Télécom Services', $model('Guru Music 2'), 2, 450_000, now()->subDays(6), by: $itManager);
    }

    /**
     * @param  array{0: string, 1: int, 2?: string}|null  $invoice  [n°, montant, 'paid' si réglée]
     */
    private function order(string $reference, string $supplier, DeviceModel $model, int $quantity, int $unitPrice, CarbonInterface $at, User $by, ?array $invoice = null): void
    {
        $order = Order::create([
            'reference' => $reference,
            'type' => OrderType::DevicePurchase,
            'status' => OrderStatus::Received,
            'supplier' => $supplier,
            'ordered_by_id' => $by->id,
            'ordered_at' => $at,
            'received_at' => $at->copy()->addDays(5),
            'total_gnf' => $quantity * $unitPrice,
            'invoice_reference' => $invoice[0] ?? null,
            'invoiced_at' => $invoice ? $at->copy()->addDays(7) : null,
            'invoice_amount_gnf' => $invoice[1] ?? null,
            'cost_center' => $invoice ? '6056-DSI' : null,
            'paid_at' => ($invoice[2] ?? null) === 'paid' ? $at->copy()->addDays(25) : null,
        ]);

        $order->items()->create([
            'device_model_id' => $model->id,
            'description' => $model->label,
            'quantity' => $quantity,
            'received_quantity' => $quantity,
            'unit_price_gnf' => $unitPrice,
        ]);
    }

    /**
     * @param  list<Role>  $roles
     */
    private function user(
        string $firstName,
        string $lastName,
        string $jobTitle,
        EmployeeCategory $category,
        Department $department,
        Site $site,
        ?User $manager = null,
        array $roles = [],
        bool $head = false,
        ?string $email = null,
    ): User {
        $email ??= strtolower(Str::ascii($firstName.'.'.$lastName)).'@agl.test';

        $user = User::updateOrCreate(['email' => $email], [
            'matricule' => str_pad((string) $this->matricule++, 4, '0', STR_PAD_LEFT),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'job_title' => $jobTitle,
            'category' => $category,
            'department_id' => $department->id,
            'site_id' => $site->id,
            'manager_id' => $manager?->id,
            'password' => $this->password,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $user->syncRoles(array_map(fn (Role $role) => $role->value, $roles));

        if ($head) {
            $department->update(['head_id' => $user->id]);
        }

        return $user;
    }

    private function device(DeviceModel $model, DeviceStatus $status): Device
    {
        return Device::create([
            'device_model_id' => $model->id,
            'imei' => Luhn::complete('35'.$this->digits(12)),
            'serial_number' => 'R5C'.strtoupper(Str::random(8)),
            'status' => $status,
            'acquired_at' => now()->subMonths(rand(1, 30)),
        ]);
    }

    /**
     * @param  list<LineOption>  $options
     */
    private function line(Operator $operator, string $number, PlanType $plan, array $options, int $recharge): SimLine
    {
        return SimLine::create([
            'operator_id' => $operator->id,
            'msisdn' => $number,
            'iccid' => Luhn::complete('8922401'.$this->digits(11)),
            'plan_type' => $plan,
            'options' => $options,
            'monthly_recharge_gnf' => $recharge,
            'is_fleet' => true,
            'status' => SimLineStatus::Active,
            'activated_at' => now()->subMonths(rand(6, 30)),
        ]);
    }

    private function digits(int $length): string
    {
        $digits = '';

        for ($i = 0; $i < $length; $i++) {
            $digits .= random_int(0, 9);
        }

        return $digits;
    }

    private function assign(User $user, Device|SimLine $item, User $by, \DateTimeInterface $at): void
    {
        Assignment::create([
            'user_id' => $user->id,
            'assignable_type' => $item->getMorphClass(),
            'assignable_id' => $item->id,
            'assigned_by_id' => $by->id,
            'assigned_at' => $at,
            'received_at' => $at,
            'policy_accepted_at' => $at,
        ]);
    }
}
