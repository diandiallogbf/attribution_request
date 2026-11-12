<?php

namespace App\Livewire\Equipment;

use App\Models\Assignment;
use App\Models\Device;
use App\Models\SimLine;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Mes équipements')]
class MyEquipment extends Component
{
    /** @return Collection<int, Assignment> */
    #[Computed]
    public function assignments(): Collection
    {
        return Assignment::query()
            ->active()
            ->where('user_id', auth()->id())
            ->with('assignable')
            ->latest('assigned_at')
            ->get()
            ->loadMorph('assignable', [Device::class => ['model'], SimLine::class => ['operator']]);
    }

    public function render(): View
    {
        return view('livewire.equipment.my-equipment', [
            'devices' => $this->assignments->filter(fn (Assignment $a) => $a->assignable instanceof Device),
            'lines' => $this->assignments->filter(fn (Assignment $a) => $a->assignable instanceof SimLine),
        ]);
    }
}
