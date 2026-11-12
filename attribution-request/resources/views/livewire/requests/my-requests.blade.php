<div>
    <x-page-header :title="__('Mes demandes')" :description="__('Les demandes que vous avez faites, et celles dont vous êtes bénéficiaire.')">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('requests.create')" wire:navigate>{{ __('Nouvelle demande') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <section class="surface">
        <div class="flex flex-col gap-3 border-b border-zinc-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
            <nav class="-mx-1 flex gap-1 overflow-x-auto" aria-label="{{ __('Filtrer par statut') }}">
                @foreach ($this->tabs() as $key => $item)
                    <button type="button" wire:click="$set('tab', '{{ $key }}')" @if($this->tab === $key) aria-current="true" @endif
                        @class([
                            'inline-flex shrink-0 items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition',
                            'bg-brand-800 text-white dark:bg-brand-300 dark:text-brand-950' => $this->tab === $key,
                            'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-white' => $this->tab !== $key,
                        ])>
                        {{ $item['label'] }}
                        <span @class([
                            'numeric rounded-full px-1.5 text-xs',
                            'bg-white/20' => $this->tab === $key,
                            'bg-zinc-100 text-zinc-500 dark:bg-white/10' => $this->tab !== $key,
                        ])>{{ $this->counts[$key] }}</span>
                    </button>
                @endforeach
            </nav>

            <div class="sm:w-64">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" size="sm" :placeholder="__('N° de fiche (ex. 2611541)')" clearable />
            </div>
        </div>

        <div wire:loading.class="opacity-60" class="transition-opacity">
            @forelse ($this->requests as $request)
                <x-request-row :request="$request" wire:key="request-{{ $request->id }}" />
            @empty
                <x-empty-state
                    :icon="$search ? 'magnifying-glass' : 'inbox'"
                    :title="$search ? __('Aucune demande ne correspond') : __('Rien ici pour le moment')"
                    :description="$search ? __('Vérifiez la référence saisie.') : __('Les demandes de cette catégorie apparaîtront ici.')" />
            @endforelse
        </div>

        @if ($this->requests->hasPages())
            <div class="border-t border-zinc-200 px-5 py-3 dark:border-white/10">
                {{ $this->requests->links() }}
            </div>
        @endif
    </section>
</div>
