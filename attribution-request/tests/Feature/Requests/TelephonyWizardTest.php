<?php

use App\Enums\RequestStatus;
use App\Livewire\Requests\ShowRequest;
use App\Livewire\Requests\TelephonyWizard;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedReferentials();
    Notification::fake();
    ['manager' => $this->manager, 'employee' => $this->employee] = team();
});

it('affiche l\'assistant aux collaborateurs connectés', function () {
    $this->actingAs($this->employee)
        ->get(route('requests.create.telephony'))
        ->assertOk()
        ->assertSee('Pour qui est cette demande ?');
});

it('redirige les invités vers la connexion', function () {
    $this->get(route('requests.create.telephony'))->assertRedirect(route('login'));
});

it('exige au moins un objet à l\'étape 2', function () {
    Livewire::actingAs($this->employee)
        ->test(TelephonyWizard::class)
        ->call('next')
        ->assertSet('step', 'subject')
        ->call('next')
        ->assertHasErrors(['subject.wantsPhone'])
        ->assertSet('step', 'subject');
});

it('valide les champs conditionnels du téléphone', function () {
    Livewire::actingAs($this->employee)
        ->test(TelephonyWizard::class)
        ->call('next')
        ->set('subject.wantsPhone', true)
        ->call('next')
        ->assertSet('step', 'phone')
        ->set('phone.reason', 'replacement')
        ->set('phone.detailedReason', 'court')
        ->call('next')
        ->assertHasErrors(['phone.phoneType', 'phone.previousDeviceChoice', 'phone.detailedReason' => 'min']);
});

it('refuse un numéro de ligne mal formé', function () {
    Livewire::actingAs($this->employee)
        ->test(TelephonyWizard::class)
        ->call('next')
        ->set('subject.wantsSim', true)
        ->call('next')
        ->set('sim.action', 'modification')
        ->set('sim.existingLineChoice', 'other')
        ->set('sim.existingMsisdn', '1234')
        ->set('sim.planType', 'prepaid')
        ->set('sim.monthlyRecharge', '100000')
        ->call('next')
        ->assertHasErrors(['sim.existingMsisdn']);
});

it('crée, enregistre et soumet une demande complète', function () {
    $component = Livewire::actingAs($this->employee)
        ->test(TelephonyWizard::class)
        ->call('next')                                   // bénéficiaire = moi
        ->set('subject.wantsPhone', true)
        ->set('subject.wantsSim', true)
        ->call('next')                                   // objet
        ->set('phone.reason', 'acquisition')
        ->set('phone.phoneType', 'samsung_android')
        ->set('phone.detailedReason', 'Prise de poste de superviseur de quai.')
        ->call('next')                                   // téléphone
        ->set('sim.action', 'creation')
        ->set('sim.planType', 'prepaid')
        ->set('sim.options', ['national_calls'])
        ->set('sim.monthlyRecharge', '200000')
        ->call('next')                                   // ligne
        ->assertSet('step', 'review')
        ->call('submit')
        ->assertHasErrors(['policyAccepted'])
        ->set('policyAccepted', true)
        ->call('submit');

    $request = ServiceRequest::query()->sole();

    $component->assertRedirect(route('requests.show', $request));

    expect($request->status)->toBe(RequestStatus::PendingManager)
        ->and($request->phoneDetail->requested_phone_type->value)->toBe('samsung_android')
        ->and($request->simDetail->requested_monthly_recharge_gnf->amount)->toBe(200_000);
});

it('permet au manager de valider depuis la fiche de la demande', function () {
    Livewire::actingAs($this->employee)
        ->test(TelephonyWizard::class)
        ->call('next')
        ->set('subject.wantsSim', true)
        ->call('next')
        ->set('sim.action', 'creation')
        ->set('sim.planType', 'prepaid')
        ->set('sim.monthlyRecharge', '100000')
        ->call('next')
        ->set('policyAccepted', true)
        ->call('submit');

    $request = ServiceRequest::query()->sole();

    Livewire::actingAs($this->manager)
        ->test(ShowRequest::class, ['serviceRequest' => $request])
        ->call('openDecision', 'returned')
        ->call('decide')
        ->assertHasErrors(['decisionComment'])
        ->call('openDecision', 'approved')
        ->call('decide')
        ->assertHasNoErrors();

    expect($request->fresh()->status)->toBe(RequestStatus::PendingDsi);
});

it('interdit de consulter la demande d\'un autre service', function () {
    $request = ServiceRequest::factory()->forBeneficiary($this->employee)->create();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)->get(route('requests.show', $request))->assertForbidden();
});
