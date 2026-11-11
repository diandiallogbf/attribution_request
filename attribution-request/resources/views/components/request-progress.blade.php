@props(['request'])

@php
    /** Les 6 étapes de la procédure (notes DSI), alignées sur RequestStatus::stage(). */
    $stages = [
        1 => __('Initiation'),
        2 => __('Validation manager'),
        3 => __('Approbation DSI'),
        4 => __('Commande'),
        5 => __('Attribution'),
        6 => __('Clôture'),
    ];
    $status = $request->status;
    $current = $status->stage();
    $stopped = in_array($status, [\App\Enums\RequestStatus::Rejected, \App\Enums\RequestStatus::Cancelled], true);
    $done = $status === \App\Enums\RequestStatus::Closed;
@endphp

<ol {{ $attributes->class('grid grid-cols-6 gap-1.5') }} aria-label="{{ __('Avancement de la demande') }}">
    @foreach ($stages as $number => $label)
        @php
            $state = match (true) {
                $done || $number < $current => 'done',
                $number === $current && $stopped => 'stopped',
                $number === $current => 'current',
                default => 'upcoming',
            };
        @endphp
        <li class="min-w-0" @if($state === 'current') aria-current="step" @endif>
            <div @class([
                'h-1.5 rounded-full',
                'bg-brand-700 dark:bg-brand-300' => $state === 'done',
                'bg-linear-to-r from-brand-700 to-brand-300 dark:from-brand-300 dark:to-brand-500' => $state === 'current',
                'bg-red-500' => $state === 'stopped',
                'bg-zinc-200 dark:bg-white/10' => $state === 'upcoming',
            ])></div>
            <p @class([
                'mt-2 hidden truncate text-xs sm:block',
                'font-semibold text-zinc-900 dark:text-white' => in_array($state, ['current', 'stopped']),
                'text-zinc-600 dark:text-zinc-300' => $state === 'done',
                'text-zinc-400 dark:text-zinc-500' => $state === 'upcoming',
            ])>{{ $label }}</p>
        </li>
    @endforeach
</ol>
<p class="mt-2 text-xs text-zinc-500 sm:hidden">{{ __('Étape :n sur 6 — :label', ['n' => $current, 'label' => $stages[$current]]) }}</p>
