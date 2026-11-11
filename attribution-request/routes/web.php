<?php

use App\Http\Controllers\RequestFormController;
use App\Livewire\Approvals\ApprovalInbox;
use App\Livewire\Dashboard;
use App\Livewire\Equipment\MyEquipment;
use App\Livewire\Requests\ChooseRequestType;
use App\Livewire\Requests\MyRequests;
use App\Livewire\Requests\ProfileWizard;
use App\Livewire\Requests\ShowRequest;
use App\Livewire\Requests\TelephonyWizard;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/tableau-de-bord')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::livewire('tableau-de-bord', Dashboard::class)->name('dashboard');

    Route::livewire('demandes', MyRequests::class)->name('requests.index');
    Route::livewire('demandes/nouvelle', ChooseRequestType::class)->name('requests.create');
    Route::livewire('demandes/nouvelle/telephonie', TelephonyWizard::class)->name('requests.create.telephony');
    Route::livewire('demandes/nouvelle/profil', ProfileWizard::class)->name('requests.create.profile');
    Route::livewire('demandes/{serviceRequest}/modifier', TelephonyWizard::class)->name('requests.edit');
    Route::livewire('demandes/{serviceRequest}/modifier-profil', ProfileWizard::class)->name('requests.profile.edit');
    Route::get('demandes/{serviceRequest}/fiche.pdf', RequestFormController::class)->name('requests.form');
    Route::livewire('demandes/{serviceRequest}', ShowRequest::class)->name('requests.show');

    Route::livewire('validations', ApprovalInbox::class)->name('approvals.index');
    Route::livewire('mes-equipements', MyEquipment::class)->name('equipment.index');
});

require __DIR__.'/settings.php';
