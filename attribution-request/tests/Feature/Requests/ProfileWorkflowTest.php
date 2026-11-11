<?php

use App\Actions\Fulfilment\ExecuteProfileRequest;
use App\Actions\Fulfilment\TransmitAndArchive;
use App\Actions\Requests\DecideApproval;
use App\Actions\Requests\SaveProfileDraft;
use App\Actions\Requests\StartProfileDraft;
use App\Actions\Requests\SubmitServiceRequest;
use App\Data\ProfileExecutionData;
use App\Data\ProfileRequestData;
use App\Enums\ApprovalDecision;
use App\Enums\ApprovalStep;
use App\Enums\AttachmentKind;
use App\Enums\EmployeeCategory;
use App\Enums\ProfileAction;
use App\Enums\ProfileType;
use App\Enums\RequestStatus;
use App\Exceptions\Domain\FulfilmentException;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Models\Application;
use App\Models\Department;
use App\Models\Direction;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\ApprovalRequested;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    seedReferentials();
    Notification::fake();
    Storage::fake('local');

    ['manager' => $this->manager] = team();
    $this->department = Department::query()->firstOrFail();

    // Directeurs métier des applications choisies : DT (Maximo) et DAF (Iris / Oscar).
    $this->dt = User::factory()->category(EmployeeCategory::Director)->create();
    $this->daf = User::factory()->category(EmployeeCategory::Director)->create();
    Direction::where('code', 'DT')->update(['director_id' => $this->dt->id]);
    Direction::where('code', 'DAF')->update(['director_id' => $this->daf->id]);

    $this->dsi = tap(User::factory()->create())->assignRole('dsi');
    $this->itAgent = tap(User::factory()->create())->assignRole('it_agent');
    $this->assistant = tap(User::factory()->create())->assignRole('assistant');
});

/** Fiche « création » pour un nouvel arrivant, soumise par son futur manager. */
function newcomerRequest(User $manager, Department $department, array $applicationCodes): ServiceRequest
{
    $draft = app(StartProfileDraft::class)->handle($manager, ProfileAction::Creation);

    app(SaveProfileDraft::class)->handle($draft, $manager, new ProfileRequestData(
        action: ProfileAction::Creation,
        firstName: 'Saran',
        lastName: 'Camara',
        jobTitle: 'Comptable',
        category: EmployeeCategory::Employee,
        departmentId: $department->id,
        siteId: $manager->site_id,
        managerId: $manager->id,
        profileType: ProfileType::Permanent,
        needsEmail: true,
        needsInternet: true,
        applicationIds: Application::query()->whereIn('code', $applicationCodes)->pluck('id')->all(),
    ));

    return app(SubmitServiceRequest::class)->handle($draft->fresh(), $manager, false);
}

it('demande la validation de chaque direction propriétaire, puis de la DSI', function () {
    $request = newcomerRequest($this->manager, $this->department, ['MAXIMO', 'IRIS', 'OSCAR']);

    // Le manager est le demandeur : validation d'office, puis 2 directions (DT, DAF) en parallèle.
    expect($request->status)->toBe(RequestStatus::PendingBusiness)
        ->and($request->reference->toStorage())->toStartWith('PRF-')
        ->and($request->beneficiary_id)->toBeNull()
        ->and($request->beneficiaryName())->toBe('Saran CAMARA');

    $business = $request->approvals()->where('step', ApprovalStep::Business)->with('direction')->get();
    expect($business->pluck('direction.code')->sort()->values()->all())->toBe(['DAF', 'DT']);

    Notification::assertSentTo([$this->dt, $this->daf], ApprovalRequested::class);

    $request = app(DecideApproval::class)->handle($request, $this->dt, ApprovalDecision::Approved);
    expect($request->status)->toBe(RequestStatus::PendingBusiness); // la DAF n'a pas encore signé

    $request = app(DecideApproval::class)->handle($request, $this->daf, ApprovalDecision::Approved);
    expect($request->status)->toBe(RequestStatus::PendingDsi);

    $request = app(DecideApproval::class)->handle($request, $this->dsi, ApprovalDecision::Approved);
    expect($request->status)->toBe(RequestStatus::Approved);
});

it('passe directement à la DSI sans application soumise à validation métier', function () {
    $request = newcomerRequest($this->manager, $this->department, ['APACHE']);

    expect($request->status)->toBe(RequestStatus::PendingDsi)
        ->and($request->approvals()->where('step', ApprovalStep::Business)->exists())->toBeFalse();
});

it('rend sans objet les autres validations métier quand un directeur renvoie la fiche', function () {
    $request = newcomerRequest($this->manager, $this->department, ['MAXIMO', 'IRIS']);

    $request = app(DecideApproval::class)->handle($request, $this->dt, ApprovalDecision::Returned, 'Préciser le périmètre Maximo.');

    expect($request->status)->toBe(RequestStatus::Returned)
        ->and($request->approvals()->where('decision', ApprovalDecision::Skipped)->count())->toBe(1);
});

it('interdit à un directeur de valider pour une autre direction', function () {
    $request = newcomerRequest($this->manager, $this->department, ['MAXIMO']);

    app(DecideApproval::class)->handle($request, $this->daf, ApprovalDecision::Approved);
})->throws(UnauthorizedActionException::class);

it('exécute la fiche, crée le compte du nouvel arrivant puis archive la fiche officielle', function () {
    $request = newcomerRequest($this->manager, $this->department, ['MAXIMO']);
    $request = app(DecideApproval::class)->handle($request, $this->dt, ApprovalDecision::Approved);
    $request = app(DecideApproval::class)->handle($request, $this->dsi, ApprovalDecision::Approved);

    $appIds = $request->profileDetail->applications()->pluck('applications.id')->all();

    $request = app(ExecuteProfileRequest::class)->handle($request, $this->itAgent, new ProfileExecutionData(
        executedApplicationIds: $appIds,
        ticket: 'INC-2026-0042',
        createPortalAccount: true,
        accountEmail: 'saran.camara@agl.test',
    ));

    expect($request->status)->toBe(RequestStatus::Executed)
        ->and($request->beneficiary->email)->toBe('saran.camara@agl.test')
        ->and($request->beneficiary->manager_id)->toBe($this->manager->id)
        ->and($request->executed_by_id)->toBe($this->itAgent->id);

    $request = app(TransmitAndArchive::class)->handle($request, $this->assistant);

    expect($request->status)->toBe(RequestStatus::Closed);

    $pdf = $request->attachments()->where('kind', AttachmentKind::RequestForm)->sole();
    Storage::disk('local')->assertExists($pdf->path);
    expect(Storage::disk('local')->get($pdf->path))->toStartWith('%PDF')
        ->and($pdf->checksum)->toBe(hash('sha256', Storage::disk('local')->get($pdf->path)));
});

it('refuse une exécution qui oublie une application demandée', function () {
    $request = newcomerRequest($this->manager, $this->department, ['APACHE']);
    $request = app(DecideApproval::class)->handle($request, $this->dsi, ApprovalDecision::Approved);

    app(ExecuteProfileRequest::class)->handle($request, $this->itAgent, new ProfileExecutionData(executedApplicationIds: []));
})->throws(FulfilmentException::class);

it('réserve la création de profil pour un nouvel arrivant aux managers', function () {
    $employee = User::factory()->create();

    app(StartProfileDraft::class)->handle($employee, ProfileAction::Creation);
})->throws(UnauthorizedActionException::class);
