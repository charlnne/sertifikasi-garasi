@props([
    'type' => 'submit',
    'variant' => 'primary',
    'href' => null,
])

@php
    $variants = [
        'primary' => 'bg-maroon-700 text-white shadow-sm hover:bg-maroon-800 hover:shadow-md',
        'accent' => 'bg-yellow-400 text-maroon-900 shadow-sm hover:bg-yellow-300 hover:shadow-md',
        'secondary' => 'border border-maroon-200 bg-white text-maroon-800 hover:border-maroon-300 hover:bg-maroon-50 hover:shadow-sm',
        'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-700 hover:shadow-md',
        'success' => 'bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 hover:shadow-md',
    ];
    $classes = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold select-none '
        .'transition duration-200 ease-out hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.97] '
        .'focus:outline-none focus-visible:ring-2 focus-visible:ring-yellow-400 focus-visible:ring-offset-2 '
        .'disabled:pointer-events-none disabled:opacity-50 '
        .($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
