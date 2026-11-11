<?php

use App\Actions\Requests\SaveTelephonyDraft;
use App\Actions\Requests\StartTelephonyDraft;
use App\Actions\Requests\SubmitServiceRequest;
use App\Data\TelephonyRequestData;
use App\Enums\ApprovalDecision;
use App\Enums\ApprovalStep;
use App\Enums\LineOption;
use App\Enums\PhoneReason;
use App\Enums\PhoneType;
use App\Enums\PlanType;
use App\Enums\RequestEventType;
use App\Enums\RequestStatus;
use App\Enums\SimAction;
use App\Exceptions\Domain\DuplicateOpenRequestException;
use App\Exceptions\Domain\MissingApproverException;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Models\Department;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\ApprovalRequested;
use App\ValueObjects\MoneyGnf;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedReferentials();
    Notification::fake();
});

function draftFor(User $requester, User $beneficiary): ServiceRequest
{
    $request = app(StartTelephonyDraft::class)->handle($requester, $beneficiary);

    return app(SaveTelephonyDraft::class)->handle($request, $requester, new TelephonyRequestData(
        wantsPhone: true,
        wantsSim: true,
        phoneReason: PhoneReason::Acquisition,
        phoneType: PhoneType::SamsungAndroid,
        detailedReason: 'Prise de poste, doit être joignable sur le quai.',
        simAction: SimAction::Creation,
        planType: PlanType::Prepaid,
        lineOptions: [LineOption::NationalCalls],
        monthlyRecharge: MoneyGnf::from(200_000),
    ));
}

it('envoie la demande au N+1 du bénéficiaire', function () {
    ['manager' => $manager, 'employee' => $employee] = team();

    $request = app(SubmitServiceRequest::class)->handle(draftFor($employee, $employee), $employee, policyAccepted: true);

    expect($request->status)->toBe(RequestStatus::PendingManager)
        ->and($request->reference->toStorage())->toBe('TEL-'.now()->year.'-00001')
        ->and($request->beneficiary_snapshot['matricule'])->toBe($employee->matricule->toStorage())
        ->and($request->policy_accepted_at)->not->toBeNull();

    $approval = $request->approvals()->sole();
    expect($approval->step)->toBe(ApprovalStep::Manager)
        ->and($approval->assigned_to_id)->toBe($manager->id)
        ->and($approval->decision)->toBe(ApprovalDecision::Pending);

    expect($request->events()->pluck('type')->all())->toBe([RequestEventType::Created, RequestEventType::Submitted]);

    Notification::assertSentTo($manager, ApprovalRequested::class);
});

it('valide d\'office l\'étape manager quand le manager initie la demande', function () {
    ['manager' => $manager, 'employee' => $employee] = team();
    $dsi = User::factory()->create();
    $dsi->assignRole('dsi');

    $request = app(SubmitServiceRequest::class)->handle(draftFor($manager, $employee), $manager, policyAccepted: true);

    expect($request->status)->toBe(RequestStatus::PendingDsi);

    $managerApproval = $request->approvals()->where('step', ApprovalStep::Manager)->sole();
    expect($managerApproval->decision)->toBe(ApprovalDecision::Approved)
        ->and($managerApproval->decided_by_id)->toBe($manager->id);

    expect($request->approvals()->where('step', ApprovalStep::Dsi)->sole()->assigned_role)->toBe('dsi');
    Notification::assertSentTo($dsi, ApprovalRequested::class);
});

it('exige l\'acceptation de la procédure de téléphonie', function () {
    ['employee' => $employee] = team();

    app(SubmitServiceRequest::class)->handle(draftFor($employee, $employee), $employee, policyAccepted: false);
})->throws(ValidationException::class);

it('refuse une deuxième demande en cours pour le même bénéficiaire', function () {
    ['employee' => $employee] = team();

    app(SubmitServiceRequest::class)->handle(draftFor($employee, $employee), $employee, true);
    app(SubmitServiceRequest::class)->handle(draftFor($employee, $employee), $employee, true);
})->throws(DuplicateOpenRequestException::class);

it('bloque la soumission si aucun valideur n\'est défini', function () {
    $orphan = User::factory()->create();

    app(SubmitServiceRequest::class)->handle(draftFor($orphan, $orphan), $orphan, true);
})->throws(MissingApproverException::class);

it('interdit de faire une demande pour quelqu\'un hors de son équipe', function () {
    ['employee' => $employee] = team();
    $stranger = User::factory()->create();

    app(StartTelephonyDraft::class)->handle($stranger, $employee);
})->throws(UnauthorizedActionException::class);

it('interdit à un manager de faire une demande pour son propre supérieur', function () {
    // La cheffe de service dirige le service où est aussi rattaché son directeur.
    $department = Department::query()->firstOrFail();
    ['manager' => $manager] = team();
    $department->update(['head_id' => $manager->id]);
    $director = User::factory()->create(['department_id' => $department->id]);
    $manager->update(['manager_id' => $director->id, 'department_id' => $department->id]);

    app(StartTelephonyDraft::class)->handle($manager->fresh(), $director);
})->throws(UnauthorizedActionException::class);

it('attribue des références séquentielles sans doublon', function () {
    $references = collect(range(1, 3))->map(function () {
        ['employee' => $employee] = team();

        return app(SubmitServiceRequest::class)->handle(draftFor($employee, $employee), $employee, true)->reference->toStorage();
    });

    $year = now()->year;
    expect($references->all())->toBe(["TEL-{$year}-00001", "TEL-{$year}-00002", "TEL-{$year}-00003"]);
});
