@props([
    'sidebar' => false,
    'inverse' => false,
])

{{-- Logo AGL + nom de l'application. « inverse » : version blanche pour fonds bleu marine. --}}
<a {{ $attributes->class('group flex items-center gap-3 rounded-lg px-1 py-1 outline-none focus-visible:ring-2 focus-visible:ring-white/40') }}>
    <img
        src="{{ asset($inverse ? 'images/brand/agl-wordmark-white.png' : 'images/brand/agl-wordmark.png') }}"
        alt="AGL — Africa Global Logistics"
        class="h-7 w-auto shrink-0 {{ $inverse ? '' : 'dark:hidden' }}"
    >
    @unless ($inverse)
        <img src="{{ asset('images/brand/agl-wordmark-white.png') }}" alt="" aria-hidden="true" class="hidden h-7 w-auto shrink-0 dark:block">
    @endunless

    <span @class([
        'border-s ps-3 leading-tight whitespace-nowrap',
        'border-white/20' => $inverse,
        'border-zinc-300 dark:border-white/20' => ! $inverse,
    ])>
        <span @class(['block text-[13px] font-semibold', 'text-white' => $inverse, 'text-zinc-900 dark:text-white' => ! $inverse])>{{ __('Portail IT') }}</span>
        <span @class(['block text-[11px]', 'text-white/60' => $inverse, 'text-zinc-500 dark:text-zinc-400' => ! $inverse])>{{ __('Téléphonie & SIM') }}</span>
    </span>
</a>
