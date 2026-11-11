<?php

use App\Actions\Requests\DecideApproval;
use App\Actions\Requests\SaveTelephonyDraft;
use App\Actions\Requests\StartTelephonyDraft;
use App\Actions\Requests\SubmitServiceRequest;
use App\Data\TelephonyRequestData;
use App\Enums\ApprovalDecision;
use App\Enums\PlanType;
use App\Enums\SimAction;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\ApprovalRequested;
use App\Notifications\RequestDecided;
use App\ValueObjects\MoneyGnf;

beforeEach(function () {
    seedReferentials();
    ['manager' => $this->manager, 'employee' => $this->employee] = team();

    $draft = app(StartTelephonyDraft::class)->handle($this->employee, $this->employee);
    app(SaveTelephonyDraft::class)->handle($draft, $this->employee, new TelephonyRequestData(
        wantsPhone: false, wantsSim: true, simAction: SimAction::Creation, planType: PlanType::Prepaid, monthlyRecharge: MoneyGnf::from(50_000),
    ));
    $this->request = app(SubmitServiceRequest::class)->handle($draft->fresh(), $this->employee, true);
});

it('enregistre réellement les notifications (cloche) en base, sans faux', function () {
    // Pas de Notification::fake() : la file est synchrone en test, le canal « database » écrit vraiment.
    expect($this->manager->notifications()->where('type', ApprovalRequested::class)->exists())->toBeTrue();

    app(DecideApproval::class)->handle($this->request, $this->manager, ApprovalDecision::Returned, 'Précisez l\'usage.');

    expect($this->employee->notifications()->where('type', RequestDecided::class)->exists())->toBeTrue();
});

it('sert la fiche officielle en PDF au demandeur', function () {
    $this->actingAs($this->employee)
        ->get(route('requests.form', $this->request))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});

it('refuse la fiche PDF à un collaborateur non concerné', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('requests.form', $this->request))
        ->assertForbidden();
});

it('n\'expose pas les brouillons en PDF', function () {
    $draft = ServiceRequest::factory()->forBeneficiary($this->employee)->create();

    $this->actingAs($this->employee)->get(route('requests.form', $draft))->assertNotFound();
});
