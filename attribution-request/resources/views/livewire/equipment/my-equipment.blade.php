<div>
    <x-page-header :title="__('Mes équipements')" :description="__('Le matériel et les lignes de la flotte AGL attribués à votre nom.')" />

    @if ($devices->isEmpty() && $lines->isEmpty())
        <section class="surface">
            <x-empty-state icon="device-phone-mobile" :title="__('Aucun équipement')" :description="__('Les téléphones et lignes qui vous seront remis apparaîtront ici.')">
                <flux:button variant="primary" size="sm" icon="plus" :href="route('requests.create')" wire:navigate>{{ __('Faire une demande') }}</flux:button>
            </x-empty-state>
        </section>
    @endif

    @if ($devices->isNotEmpty())
        <h2 class="eyebrow mb-3">{{ __('Téléphones') }}</h2>
        <div class="mb-10 grid gap-4 md:grid-cols-2">
            @foreach ($devices as $assignment)
                @php $device = $assignment->assignable; @endphp
                <article class="surface overflow-hidden">
                    <div class="flex items-start gap-4 p-5">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-linear-to-br from-brand-700 to-brand-900 text-white shadow-sm">
                            <flux:icon :icon="$device->model->phone_type->icon()" class="size-6" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-zinc-900 dark:text-white">{{ $device->model->brand }} {{ $device->model->name }}</p>
                            <p class="text-sm text-zinc-500">{{ $device->model->phone_type->getLabel() }}@if($device->model->storage_gb) · {{ $device->model->storage_gb }} Go @endif</p>
                        </div>
                    </div>
                    <dl class="grid grid-cols-2 gap-4 border-t border-zinc-100 px-5 py-4 text-sm dark:border-white/5">
                        <div><dt class="text-xs text-zinc-500">{{ __('IMEI') }}</dt><dd class="numeric mt-0.5 font-mono text-[13px] text-zinc-900 dark:text-zinc-100">{{ $device->imei?->formatted() ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-zinc-500">{{ __('N° de série') }}</dt><dd class="mt-0.5 font-mono text-[13px] text-zinc-900 dark:text-zinc-100">{{ $device->serial_number ?? '—' }}</dd></div>
                        <div><dt class="text-xs text-zinc-500">{{ __('Attribué le') }}</dt><dd class="mt-0.5 text-zinc-900 dark:text-zinc-100">{{ $assignment->assigned_at->translatedFormat('d F Y') }}</dd></div>
                        <div><dt class="text-xs text-zinc-500">{{ __('Ancienneté') }}</dt><dd class="mt-0.5 text-zinc-900 dark:text-zinc-100">{{ $assignment->assigned_at->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</dd></div>
                    </dl>
                    <div class="flex flex-wrap gap-2 border-t border-zinc-100 bg-zinc-50/60 px-5 py-3 dark:border-white/5 dark:bg-white/[0.02]">
                        <flux:button size="xs" variant="ghost" icon="wrench-screwdriver" :href="route('requests.create', ['motif' => 'repair'])" wire:navigate>{{ __('Signaler une panne') }}</flux:button>
                        <flux:button size="xs" variant="ghost" icon="arrow-path" :href="route('requests.create', ['motif' => 'replacement'])" wire:navigate>{{ __('Demander un remplacement') }}</flux:button>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

    @if ($lines->isNotEmpty())
        <h2 class="eyebrow mb-3">{{ __('Lignes') }}</h2>
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($lines as $assignment)
                @php $line = $assignment->assignable; @endphp
                <article class="surface p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="numeric text-lg font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $line->msisdn->international() }}</p>
                            <p class="text-sm text-zinc-500">{{ $line->operator->name }} · {{ $line->plan_type->getLabel() }}</p>
                        </div>
                        <x-status-badge :status="$line->status" />
                    </div>
                    <div class="mt-4 flex flex-wrap items-center gap-1.5">
                        @foreach ($line->options ?? [] as $option)
                            <span class="rounded-md bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-800 ring-1 ring-brand-700/15 dark:bg-brand-400/10 dark:text-brand-200">{{ $option->code() }} · {{ $option->getLabel() }}</span>
                        @endforeach
                    </div>
                    @if ($line->monthly_recharge_gnf)
                        <p class="mt-4 flex items-baseline justify-between border-t border-zinc-100 pt-3 text-sm dark:border-white/5">
                            <span class="text-zinc-500">{{ __('Recharge mensuelle') }}</span>
                            <span class="numeric font-semibold text-zinc-900 dark:text-white">{{ $line->monthly_recharge_gnf->format() }}</span>
                        </p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</div>
