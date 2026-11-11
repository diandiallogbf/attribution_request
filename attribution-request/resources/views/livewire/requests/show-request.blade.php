@use('App\Enums\ApprovalDecision')
@use('App\Enums\RequestStatus')
@use('App\Enums\RequestType')

@php
    $request = $this->serviceRequest;
    $status = $request->status;
    $user = auth()->user();
    $canDecide = $user->can('decide', $request);
    $canEdit = $user->can('update', $request);
    $canCancel = $user->can('cancel', $request);
    $myApproval = $request->pendingApprovalFor($user);
    $lastDecision = $request->approvals->whereIn('decision', [ApprovalDecision::Returned, ApprovalDecision::Rejected])->sortByDesc('decided_at')->first();
    $isProfile = $request->type === RequestType::UserProfile;
    $archived = $request->archivedForms->first();
@endphp

<div>
    @if (session('submitted'))
        <div class="mb-6 flex items-start gap-4 rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 dark:border-emerald-400/20 dark:bg-emerald-400/5">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300">
                <flux:icon.paper-airplane class="size-5" />
            </span>
            <div>
                <p class="font-semibold text-emerald-900 dark:text-emerald-200">{{ __('Demande envoyée') }}</p>
                <p class="text-sm text-emerald-800/80 dark:text-emerald-200/70">{{ session('submitted') }} {{ __('Vous serez notifié par e-mail à chaque étape.') }}</p>
            </div>
        </div>
    @endif

    <x-page-header :back="route('requests.index')"
        :eyebrow="$request->type->formTitle().' · '.$request->displayReference()"
        :title="$request->subjectLabel().($request->beneficiary_id !== $user->id ? ' -'.$request->beneficiaryName() : '')">
        <x-slot:actions>
            <x-status-badge :status="$status" size="md" />
            @if ($status !== RequestStatus::Draft)
                <flux:button size="sm" variant="ghost" icon="document-arrow-down" :href="route('requests.form', $request)" target="_blank">
                    {{ $archived ? __('Fiche archivée') : __('Aperçu de la fiche') }}
                </flux:button>
            @endif
            @if ($canEdit)
                <flux:button size="sm" icon="pencil-square" :href="$request->editUrl()" wire:navigate>
                    {{ $status === RequestStatus::Returned ? __('Corriger') : __('Reprendre') }}
                </flux:button>
            @endif
            @if ($canCancel)
                <flux:modal.trigger name="cancel">
                    <flux:button size="sm" variant="ghost" icon="x-mark">{{ __('Annuler') }}</flux:button>
                </flux:modal.trigger>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Décision attendue --}}
    @if ($canDecide && $myApproval)
        <section class="mb-6 overflow-hidden rounded-xl border border-brand-200 bg-linear-to-r from-brand-50 to-white shadow-(--shadow-card) dark:border-brand-300/20 dark:from-brand-400/10 dark:to-zinc-900">
            <div class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-800 text-white dark:bg-brand-500">
                    <flux:icon.check-badge class="size-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-zinc-900 dark:text-white">{{ __('Votre décision est attendue') }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $myApproval->label() }} ·
                        {{ __('soumise par :name :ago', ['name' => $request->requester->name, 'ago' => $request->submitted_at?->diffForHumans()]) }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" variant="ghost" icon="arrow-uturn-left" wire:click="openDecision('returned')">{{ __('Renvoyer') }}</flux:button>
                    <flux:button size="sm" variant="danger" icon="x-mark" wire:click="openDecision('rejected')">{{ __('Rejeter') }}</flux:button>
                    <flux:button size="sm" variant="primary" icon="check" wire:click="openDecision('approved')">{{ __('Valider') }}</flux:button>
                </div>
            </div>
        </section>
    @endif

    {{-- Confirmation de réception (bénéficiaire) --}}
    @if ($this->canConfirmReceipt())
        <section class="mb-6 overflow-hidden rounded-xl border border-emerald-200 bg-white shadow-(--shadow-card) dark:border-emerald-400/20 dark:bg-zinc-900">
            <div class="flex items-center gap-3 border-b border-emerald-100 bg-emerald-50 px-5 py-4 dark:border-emerald-400/10 dark:bg-emerald-400/5">
                <flux:icon.gift class="size-5 text-emerald-700 dark:text-emerald-300" />
                <div>
                    <p class="font-semibold text-emerald-900 dark:text-emerald-200">{{ __('Votre matériel est prêt') }}</p>
                    <p class="text-sm text-emerald-800/80 dark:text-emerald-200/70">{{ __('Vérifiez-le, puis confirmez la réception : la demande sera clôturée et la fiche archivée.') }}</p>
                </div>
            </div>
            <div class="grid gap-3 px-5 py-4 sm:grid-cols-2">
                @foreach ($this->handedOver as $assignment)
                    @php $item = $assignment->assignable; @endphp
                    <div class="flex items-center gap-3 rounded-lg border border-zinc-200 px-4 py-3 dark:border-white/10">
                        <flux:icon :icon="$item instanceof \App\Models\Device ? 'device-phone-mobile' : 'signal'" class="size-5 text-zinc-400" />
                        <div class="min-w-0 text-sm">
                            <p class="truncate font-semibold text-zinc-900 dark:text-white">{{ $item instanceof \App\Models\Device ? $item->model->label : $item->msisdn->international() }}</p>
                            <p class="numeric truncate text-xs text-zinc-500">
                                {{ $item instanceof \App\Models\Device ? ($item->imei ? 'IMEI '.$item->imei->formatted() : $item->serial_number) : $item->plan_type->getLabel().($item->monthly_recharge_gnf ? ' · '.$item->monthly_recharge_gnf->format().'/mois' : '') }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
            <form wire:submit="confirmReceipt" class="flex flex-col gap-4 border-t border-zinc-100 px-5 py-4 sm:flex-row sm:items-center dark:border-white/5">
                <div class="flex-1">
                    <flux:checkbox wire:model="receiptPolicyAccepted" :label="__('Je m\'engage sans réserve à respecter la procédure de gestion de la téléphonie.')" />
                </div>
                <flux:button type="submit" variant="primary" icon="hand-thumb-up">{{ __('Je confirme la réception') }}</flux:button>
            </form>
        </section>
    @endif

    @if ($lastDecision && in_array($status, [RequestStatus::Returned, RequestStatus::Rejected], true))
        <flux:callout :icon="$status === RequestStatus::Returned ? 'arrow-uturn-left' : 'x-circle'" :color="$status === RequestStatus::Returned ? 'orange' : 'red'" class="mb-6">
            <flux:callout.heading>
                {{ $status === RequestStatus::Returned ? __('Renvoyée pour correction par :name', ['name' => $lastDecision->decidedBy?->name]) : __('Rejetée par :name', ['name' => $lastDecision->decidedBy?->name]) }}
            </flux:callout.heading>
            <flux:callout.text>« {{ $lastDecision->comment }} »</flux:callout.text>
        </flux:callout>
    @endif

    {{-- Avancement --}}
    <section class="surface mb-6 px-5 py-5">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Avancement') }}</h2>
            @if ($request->closed_at)
                <p class="text-xs text-zinc-500">{{ __('Clôturée le :date', ['date' => $request->closed_at->translatedFormat('d F Y à H:i')]) }}</p>
            @elseif ($request->submitted_at)
                <p class="text-xs text-zinc-500">{{ __('Soumise le :date', ['date' => $request->submitted_at->translatedFormat('d F Y à H:i')]) }}</p>
            @endif
        </div>
        <x-request-progress :request="$request" />
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @include($isProfile ? 'livewire.requests.show.profile' : 'livewire.requests.show.telephony')

            @if ($request->executed_at)
                <section class="surface px-5 py-4">
                    <div class="flex items-center gap-2 text-sm font-semibold text-zinc-900 dark:text-white">
                        <flux:icon.wrench-screwdriver variant="micro" class="size-4 text-zinc-400" /> {{ __('Exécution') }}
                    </div>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('Exécutée le :date par :name', ['date' => $request->executed_at->translatedFormat('d F Y à H:i'), 'name' => $request->executedBy?->name]) }}
                        @if ($request->profileDetail?->execution_ticket) · {{ __('Ticket') }} <span class="font-mono">{{ $request->profileDetail->execution_ticket }}</span> @endif
                    </p>
                    @if ($request->execution_notes)
                        <p class="mt-2 rounded-lg bg-zinc-50 px-3 py-2 text-[13px] text-zinc-700 dark:bg-white/5 dark:text-zinc-300">{{ $request->execution_notes }}</p>
                    @endif
                </section>
            @endif

            @if ($request->comment)
                <section class="surface px-5 py-4">
                    <p class="text-xs text-zinc-500">{{ __('Commentaire du demandeur') }}</p>
                    <p class="mt-1 text-sm whitespace-pre-line text-zinc-800 dark:text-zinc-200">{{ $request->comment }}</p>
                </section>
            @endif
        </div>

        {{-- Colonne latérale --}}
        <aside class="space-y-6">
            <section class="surface">
                <header class="border-b border-zinc-200 px-5 py-4 dark:border-white/10">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Validations') }}</h2>
                </header>
                <ul class="divide-y divide-zinc-100 dark:divide-white/5">
                    @forelse ($request->approvals->sortBy('id') as $approval)
                        <li class="flex items-start gap-3 px-5 py-3.5">
                            <x-status-badge :status="$approval->decision" class="mt-0.5" />
                            <div class="min-w-0 text-sm">
                                <p class="font-medium text-zinc-900 dark:text-white">{{ $approval->label() }}</p>
                                <p class="truncate text-xs text-zinc-500">
                                    @if ($approval->decidedBy)
                                        {{ $approval->decidedBy->name }} · {{ $approval->decided_at?->translatedFormat('d M Y, H:i') }}
                                    @else
                                        {{ $approval->assignedTo?->name ?? __('Équipe DSI') }}
                                    @endif
                                </p>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-sm text-zinc-500">{{ __('Aucune validation pour l\'instant.') }}</li>
                    @endforelse
                </ul>
            </section>

            @if ($archived)
                <a href="{{ route('requests.form', $request) }}" target="_blank" class="surface group flex items-center gap-3 px-5 py-4 transition hover:border-zinc-300 dark:hover:border-white/20">
                    <span class="flex size-10 items-center justify-center rounded-lg bg-red-50 text-red-600 ring-1 ring-red-600/10 dark:bg-red-400/10 dark:text-red-300">
                        <flux:icon.document-text class="size-5" />
                    </span>
                    <div class="min-w-0 flex-1 text-sm">
                        <p class="font-semibold text-zinc-900 dark:text-white">{{ __('Fiche officielle archivée') }}</p>
                        <p class="truncate font-mono text-[11px] text-zinc-500" title="SHA-256 {{ $archived->checksum }}">SHA-256 {{ \Illuminate\Support\Str::limit($archived->checksum, 16, '…') }}</p>
                    </div>
                    <flux:icon.arrow-down-tray variant="micro" class="size-4 text-zinc-400 group-hover:text-zinc-600" />
                </a>
            @endif

            <section class="surface">
                <header class="border-b border-zinc-200 px-5 py-4 dark:border-white/10">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Historique') }}</h2>
                </header>
                <ol class="px-5 py-5">
                    @foreach ($request->events->reverse() as $event)
                        <li class="relative flex gap-3 pb-5 last:pb-0">
                            @unless ($loop->last)
                                <span class="absolute top-7 left-[13px] h-[calc(100%-1.75rem)] w-px bg-zinc-200 dark:bg-white/10" aria-hidden="true"></span>
                            @endunless
                            <span @class([
                                'relative flex size-7 shrink-0 items-center justify-center rounded-full ring-4 ring-white dark:ring-zinc-900',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300' => $event->type->getColor() === 'success',
                                'bg-orange-100 text-orange-700 dark:bg-orange-400/15 dark:text-orange-300' => $event->type->getColor() === 'orange',
                                'bg-red-100 text-red-700 dark:bg-red-400/15 dark:text-red-300' => $event->type->getColor() === 'danger',
                                'bg-violet-100 text-violet-700 dark:bg-violet-400/15 dark:text-violet-300' => $event->type->getColor() === 'violet',
                                'bg-zinc-100 text-zinc-500 dark:bg-white/10 dark:text-zinc-300' => $event->type->getColor() === 'gray',
                            ])>
                                <flux:icon :icon="$event->type->icon()" variant="micro" class="size-3.5" />
                            </span>
                            <div class="min-w-0 pt-0.5 text-sm">
                                <p class="font-medium text-zinc-900 dark:text-white">
                                    {{ $event->type->getLabel() }}
                                    @if (! empty($event->changes['direction']))<span class="font-normal text-zinc-500">· {{ $event->changes['direction'] }}</span>@endif
                                </p>
                                <p class="text-xs text-zinc-500">
                                    {{ $event->actor?->name ?? __('Système') }} ·
                                    <time datetime="{{ $event->created_at->toIso8601String() }}" title="{{ $event->created_at->translatedFormat('d F Y à H:i') }}">{{ $event->created_at->diffForHumans() }}</time>
                                </p>
                                @if ($event->comment)
                                    <p class="mt-1.5 rounded-lg bg-zinc-50 px-3 py-2 text-[13px] text-zinc-700 dark:bg-white/5 dark:text-zinc-300">{{ $event->comment }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>

    {{-- Modale de décision --}}
    <flux:modal name="decision" class="w-full max-w-lg">
        @php $decision = ApprovalDecision::tryFrom((string) $pendingDecision); @endphp
        <form wire:submit="decide" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    @switch($decision)
                        @case(ApprovalDecision::Approved) {{ __('Valider la demande :ref', ['ref' => $request->displayReference()]) }} @break
                        @case(ApprovalDecision::Returned) {{ __('Renvoyer pour correction') }} @break
                        @case(ApprovalDecision::Rejected) {{ __('Rejeter la demande') }} @break
                    @endswitch
                </flux:heading>
                <flux:text class="mt-2">
                    @switch($decision)
                        @case(ApprovalDecision::Approved) {{ __('La demande passera à l\'étape suivante et le demandeur sera notifié. Votre validation est horodatée.') }} @break
                        @case(ApprovalDecision::Returned) {{ __('Le demandeur pourra modifier sa demande puis la soumettre à nouveau.') }} @break
                        @case(ApprovalDecision::Rejected) {{ __('La demande sera définitivement close. Cette action est irréversible.') }} @break
                    @endswitch
                </flux:text>
            </div>

            <flux:textarea wire:model="decisionComment" rows="3"
                :label="$decision?->requiresComment() ? __('Motif (obligatoire)') : __('Commentaire (facultatif)')"
                :placeholder="$decision === ApprovalDecision::Returned ? __('Ex. : précisez le modèle du téléphone à remplacer.') : ''" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Annuler') }}</flux:button></flux:modal.close>
                <flux:button type="submit" :variant="$decision === ApprovalDecision::Rejected ? 'danger' : 'primary'">{{ __('Confirmer') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modale d'annulation --}}
    <flux:modal name="cancel" class="w-full max-w-md">
        <form wire:submit="cancel" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Annuler cette demande ?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Les validations en cours seront interrompues. Vous pourrez créer une nouvelle demande à tout moment.') }}</flux:text>
            </div>
            <flux:input wire:model="cancelReason" :label="__('Raison')" :badge="__('Facultatif')" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Retour') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Annuler la demande') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
