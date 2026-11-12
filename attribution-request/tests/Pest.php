<?php

use App\Actions\Requests\ApproveByDsi;
use App\Actions\Requests\DecideApproval;
use App\Actions\Requests\SaveTelephonyDraft;
use App\Actions\Requests\StartTelephonyDraft;
use App\Actions\Requests\SubmitServiceRequest;
use App\Data\TelephonyRequestData;
use App\Enums\ApprovalDecision;
use App\Enums\EmployeeCategory;
use App\Enums\PhoneReason;
use App\Enums\PhoneType;
use App\Enums\PlanType;
use App\Enums\SimAction;
use App\Models\DeviceModel;
use App\Models\ServiceRequest;
use App\Models\SimLine;
use App\Models\User;
use App\ValueObjects\MoneyGnf;
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

/** Demande téléphone (+ ligne facultative) approuvée par le manager et la DSI. */
function approvedTelephonyRequest(User $employee, User $manager, User $dsi, DeviceModel $model, ?SimAction $simAction = SimAction::Creation, ?SimLine $line = null): ServiceRequest
{
    $draft = app(StartTelephonyDraft::class)->handle($employee, $employee);
    app(SaveTelephonyDraft::class)->handle($draft, $employee, new TelephonyRequestData(
        wantsPhone: $simAction !== SimAction::Modification,
        wantsSim: $simAction !== null,
        phoneReason: PhoneReason::Acquisition,
        phoneType: PhoneType::SamsungAndroid,
        detailedReason: 'Nouveau superviseur de quai, joignable en permanence.',
        simAction: $simAction,
        planType: PlanType::Prepaid,
        existingSimLineId: $line?->id,
        monthlyRecharge: MoneyGnf::from(200_000),
    ));

    $request = app(SubmitServiceRequest::class)->handle($draft->fresh(), $employee, true);
    $request = app(DecideApproval::class)->handle($request, $manager, ApprovalDecision::Approved);

    return app(ApproveByDsi::class)->handle(
        $request,
        $dsi,
        $request->phoneDetail ? $model : null,
        $simAction?->requiresPlan() ? MoneyGnf::from(200_000) : null,
    );
}
