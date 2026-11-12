<?php

use App\Actions\Requests\CancelServiceRequest;
use App\Actions\Requests\DecideApproval;
use App\Actions\Requests\SaveTelephonyDraft;
use App\Actions\Requests\StartTelephonyDraft;
use App\Actions\Requests\SubmitServiceRequest;
use App\Data\TelephonyRequestData;
use App\Enums\ApprovalDecision;
use App\Enums\ApprovalStep;
use App\Enums\PhoneReason;
use App\Enums\PhoneType;
use App\Enums\RequestStatus;
use App\Exceptions\Domain\InvalidTransitionException;
use App\Exceptions\Domain\UnauthorizedActionException;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\RequestDecided;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    seedReferentials();
    Notification::fake();

    ['manager' => $this->manager, 'employee' => $this->employee] = team();
    $this->dsi = User::factory()->create();
    $this->dsi->assignRole('dsi');

    $draft = app(StartTelephonyDraft::class)->handle($this->employee, $this->employee);
    app(SaveTelephonyDraft::class)->handle($draft, $this->employee, new TelephonyRequestData(
        wantsPhone: true,
        wantsSim: false,
        phoneReason: PhoneReason::Acquisition,
        phoneType: PhoneType::SamsungAndroid,
        detailedReason: 'Nouveau superviseur, doit être joignable en permanence.',
    ));

    $this->request = app(SubmitServiceRequest::class)->handle($draft, $this->employee, true);
});

function decide(ServiceRequest $request, User $actor, ApprovalDecision $decision, ?string $comment = null): ServiceRequest
{
    return app(DecideApproval::class)->handle($request, $actor, $decision, $comment);
}

it('fait passer la demande du manager à la DSI puis l\'approuve', function () {
    $request = decide($this->request, $this->manager, ApprovalDecision::Approved);

    expect($request->status)->toBe(RequestStatus::PendingDsi)
        ->and($request->approvals()->where('step', ApprovalStep::Dsi)->pending()->exists())->toBeTrue();

    $request = decide($request, $this->dsi, ApprovalDecision::Approved, 'Conforme à la grille.');

    expect($request->status)->toBe(RequestStatus::Approved);
    Notification::assertSentTo($this->employee, RequestDecided::class);
});

it('trace la signature électronique du valideur', function () {
    decide($this->request, $this->manager, ApprovalDecision::Approved);

    $approval = $this->request->approvals()->where('step', ApprovalStep::Manager)->sole();

    expect($approval->decided_by_id)->toBe($this->manager->id)
        ->and($approval->decided_at)->not->toBeNull()
        ->and($approval->ip_address)->not->toBeNull();
});

it('exige un motif pour renvoyer ou rejeter', function (ApprovalDecision $decision) {
    decide($this->request, $this->manager, $decision);
})->throws(ValidationException::class)->with([ApprovalDecision::Returned, ApprovalDecision::Rejected]);

it('permet au demandeur de corriger puis de renvoyer une demande', function () {
    decide($this->request, $this->manager, ApprovalDecision::Returned, 'Précisez le besoin.');

    expect($this->request->fresh()->status)->toBe(RequestStatus::Returned);

    $request = app(SubmitServiceRequest::class)->handle($this->request->fresh(), $this->employee, true);

    expect($request->status)->toBe(RequestStatus::PendingManager)
        ->and($request->approvals()->pending()->count())->toBe(1);
});

it('refuse la décision d\'une personne qui n\'est pas le valideur attendu', function () {
    decide($this->request, $this->dsi, ApprovalDecision::Approved);
})->throws(UnauthorizedActionException::class);

it('refuse de décider deux fois la même étape', function () {
    decide($this->request, $this->manager, ApprovalDecision::Rejected, 'Hors budget.');
    decide($this->request->fresh(), $this->manager, ApprovalDecision::Approved);
})->throws(InvalidTransitionException::class);

it('permet au demandeur d\'annuler tant que la DSI n\'a pas traité', function () {
    $request = app(CancelServiceRequest::class)->handle($this->request, $this->employee, 'Plus nécessaire.');

    expect($request->status)->toBe(RequestStatus::Cancelled)
        ->and($request->approvals()->pending()->exists())->toBeFalse();
});
