@props(['inverse' => false])

<img
    src="{{ asset($inverse ? 'images/brand/agl-globe-white.png' : 'images/brand/agl-globe.png') }}"
    alt="AGL"
    {{ $attributes->class('w-auto') }}
>
