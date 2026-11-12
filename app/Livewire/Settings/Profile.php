<?php

namespace App\Livewire\Settings;

use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Profil en lecture seule : l'identité vient de l'annuaire (RH aujourd'hui, Microsoft Entra ID demain).
 * Le collaborateur ne la modifie pas lui-même.
 */
#[Title('Mon profil')]
class Profile extends Component
{
    public function render(): View
    {
        return view('livewire.settings.profile', [
            'user' => auth()->user()->load(['department.direction', 'site', 'manager']),
        ]);
    }
}
