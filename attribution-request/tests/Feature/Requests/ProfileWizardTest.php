<?php

use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Livewire\Requests\ProfileWizard;
use App\Models\Application;
use App\Models\Department;
use App\Models\ServiceRequest;
use App\Models\Site;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    seedReferentials();
    Notification::fake();
    ['manager' => $this->manager, 'employee' => $this->employee] = team();
});

it('propose les deux fiches sur « Nouvelle demande »', function () {
    $this->actingAs($this->employee)
        ->get(route('requests.create'))
        ->assertOk()
        ->assertSee('Téléphonie')
        ->assertSee('Profil utilisateur');
});

it('valide l\'identité du nouvel arrivant', function () {
    Livewire::actingAs($this->manager)
        ->test(ProfileWizard::class)
        ->set('profile.action', 'creation')
        ->call('next')
        ->assertSet('step', 'person')
        ->set('profile.matricule', '12')
        ->set('profile.profileType', 'internship')
        ->call('next')
        ->assertHasErrors(['profile.firstName', 'profile.lastName', 'profile.jobTitle', 'profile.matricule', 'profile.internshipEndsAt'])
        ->assertSet('step', 'person');
});

it('crée et soumet une fiche profil pour un nouvel arrivant', function () {
    $department = Department::query()->firstOrFail();

    Livewire::actingAs($this->manager)
        ->test(ProfileWizard::class)
        ->set('profile.action', 'creation')
        ->call('next')
        ->set('profile.firstName', 'Saran')
        ->set('profile.lastName', 'Camara')
        ->set('profile.jobTitle', 'Comptable')
        ->set('profile.category', 'employee')
        ->set('profile.departmentId', $department->id)
        ->set('profile.siteId', Site::query()->value('id'))
        ->set('profile.profileType', 'permanent')
        ->call('next')
        ->assertSet('step', 'access')
        ->set('profile.needsEmail', true)
        ->call('next')
        ->set('profile.applicationIds', [(string) Application::query()->where('code', 'APACHE')->value('id')])
        ->call('next')
        ->call('next')
        ->assertSet('step', 'review')
        ->call('submit')
        ->assertHasNoErrors();

    $request = ServiceRequest::query()->sole();

    expect($request->type)->toBe(RequestType::UserProfile)
        ->and($request->status)->toBe(RequestStatus::PendingDsi) // manager demandeur + aucune validation métier
        ->and($request->profileDetail->first_name)->toBe('Saran');
});

it('réduit l\'assistant à l\'essentiel pour une suppression', function () {
    Livewire::actingAs($this->manager)
        ->test(ProfileWizard::class)
        ->set('profile.action', 'deletion')
        ->call('next')
        ->set('profile.beneficiaryId', $this->employee->id)
        ->call('next')
        ->assertHasErrors(['profile.effectiveAt'])
        ->set('profile.effectiveAt', now()->addWeek()->toDateString())
        ->call('next')
        ->assertSet('step', 'review')
        ->call('submit')
        ->assertHasNoErrors();

    expect(ServiceRequest::query()->sole()->beneficiary_id)->toBe($this->employee->id);
});
