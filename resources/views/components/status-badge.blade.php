@props(['status'])

@php
    $colors = [
        'pending' => 'bg-yellow-100 text-yellow-900 ring-yellow-300',
        'verified' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'rejected' => 'bg-red-50 text-red-700 ring-red-200',
        'active' => 'bg-maroon-50 text-maroon-700 ring-maroon-200',
        'finished' => 'bg-stone-100 text-stone-600 ring-stone-200',
        'cancelled' => 'bg-red-50 text-red-700 ring-red-200',
        'available' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'booked' => 'bg-yellow-100 text-yellow-900 ring-yellow-300',
        'used' => 'bg-maroon-100 text-maroon-800 ring-maroon-200',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 '.($colors[$status->value] ?? 'bg-stone-100 text-stone-700 ring-stone-200')]) }}>
    {{ $status->label() }}
</span>
