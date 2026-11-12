<div>
    <x-page-header :title="__('À valider')"
        :description="__('Les demandes qui attendent votre décision, de la plus ancienne à la plus récente.')" />

    @if ($this->approvals->isEmpty())
        <section class="surface">
            <x-empty-state icon="check-circle" :title="__('Tout est à jour')" :description="__('Aucune demande n\'attend votre validation. Vous serez notifié par e-mail dès qu\'une nouvelle arrive.')" />
        </section>
    @else
        <ul class="space-y-3">
            @foreach ($this->approvals as $approval)
                @php
                    $request = $approval->serviceRequest;
                    $person = $request->beneficiary;
                    $compliant = $this->compliance[$approval->id] ?? true;
                    $waitingDays = (int) $request->submitted_at?->diffInDays(now());
                @endphp
                <li wire:key="approval-{{ $approval->id }}">
                    <a href="{{ route('requests.show', $request) }}" wire:navigate
                       class="surface group flex flex-col gap-4 p-5 transition hover:border-zinc-300 hover:shadow-(--shadow-raised) sm:flex-row sm:items-center dark:hover:border-white/20">
                        <div class="flex min-w-0 flex-1 items-center gap-4">
                            <flux:avatar :name="$request->beneficiaryName()" :initials="$request->beneficiaryInitials()" />
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-zinc-900 dark:text-white">{{ $request->beneficiaryName() }}
                                    <span class="font-normal text-zinc-500">· {{ $person?->job_title ?? ($request->beneficiary_snapshot['job_title'] ?? __('Nouvel arrivant')) }}</span>
                                </p>
                                <p class="mt-0.5 truncate text-sm text-zinc-600 dark:text-zinc-400">
                                    <span @class(['me-1 rounded px-1.5 py-0.5 text-[11px] font-medium',
                                        'bg-brand-50 text-brand-800 dark:bg-brand-400/10 dark:text-brand-200' => $request->type === \App\Enums\RequestType::Telephony,
                                        'bg-violet-50 text-violet-700 dark:bg-violet-400/10 dark:text-violet-300' => $request->type === \App\Enums\RequestType::UserProfile,
                                    ])>{{ $request->type->getLabel() }}</span>
                                    {{ $request->subjectLabel() }}
                                    @if ($request->phoneDetail?->reason) — {{ $request->phoneDetail->reason->getLabel() }} @endif
                                    @if ($request->simDetail?->requested_monthly_recharge_gnf) · {{ $request->simDetail->requested_monthly_recharge_gnf->format() }}/mois @endif
                                </p>
                                <p class="numeric mt-1 text-xs text-zinc-500">
                                    <span class="font-mono">{{ $request->displayReference() }}</span> · {{ $approval->label() }} · {{ __('soumise :ago', ['ago' => $request->submitted_at?->diffForHumans()]) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @unless ($compliant)
                                <span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-800 ring-1 ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300">{{ __('Hors grille') }}</span>
                            @endunless
                            @if ($waitingDays >= 2)
                                <span class="rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-red-600/20 dark:bg-red-400/10 dark:text-red-300">{{ trans_choice('En attente depuis :count jour|En attente depuis :count jours', $waitingDays) }}</span>
                            @endif
                            <span class="inline-flex items-center gap-1 rounded-lg bg-brand-800 px-3 py-1.5 text-sm font-medium text-white transition group-hover:bg-brand-700 dark:bg-brand-300 dark:text-brand-950">
                                {{ __('Examiner') }} <flux:icon.arrow-right variant="micro" class="size-4" />
                            </span>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
