@props(['request', 'showBeneficiary' => true])

@php
    $isDraft = $request->status === \App\Enums\RequestStatus::Draft;
    $href = $isDraft ? $request->editUrl() : route('requests.show', $request);
    $forSomeoneElse = $request->beneficiary_id !== auth()->id();
@endphp

<a href="{{ $href }}" wire:navigate {{ $attributes->class('group flex items-center gap-4 border-b border-zinc-100 px-5 py-4 transition last:border-b-0 hover:bg-zinc-50/80 dark:border-white/5 dark:hover:bg-white/[0.02]') }}>
    <span @class([
        'flex size-10 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset',
        'bg-brand-50 text-brand-700 ring-brand-700/10 dark:bg-brand-400/10 dark:text-brand-200' => $request->type === \App\Enums\RequestType::Telephony,
        'bg-violet-50 text-violet-700 ring-violet-600/10 dark:bg-violet-400/10 dark:text-violet-300' => $request->type === \App\Enums\RequestType::UserProfile,
    ])>
        <flux:icon :icon="$request->subjectIcon()" variant="outline" class="size-5" />
    </span>

    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
            <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $request->subjectLabel() }}</p>
            @if ($showBeneficiary && $forSomeoneElse)
                <span class="hidden truncate text-sm text-zinc-500 sm:inline">· {{ __('pour :name', ['name' => $request->beneficiaryName()]) }}</span>
            @endif
        </div>
        <p class="numeric mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">
            <span class="font-mono">{{ $request->displayReference() }}</span>
            · {{ __('mise à jour :ago', ['ago' => $request->updated_at->diffForHumans()]) }}
        </p>
        <x-status-badge :status="$request->status" class="mt-1.5 sm:hidden" />
    </div>

    <x-status-badge :status="$request->status" class="hidden sm:inline-flex" />
    <flux:icon.chevron-right variant="micro" class="size-4 shrink-0 text-zinc-300 transition group-hover:translate-x-0.5 group-hover:text-zinc-500 dark:text-zinc-600" />
</a>
