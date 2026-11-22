@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
])

<div class="mb-4">
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-stone-700">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password' && $type !== 'file') value="{{ old($name, $value) }}" @endif
        {{ $attributes->class([
            'w-full rounded-lg border bg-white px-3 py-2 text-sm shadow-sm focus:border-maroon-500 focus:outline-none focus:ring-2 focus:ring-maroon-200',
            'file:mr-3 file:rounded-md file:border-0 file:bg-maroon-50 file:px-3 file:py-1 file:text-sm file:font-medium file:text-maroon-700' => $type === 'file',
            'border-red-500' => $errors->has($name),
            'border-stone-300' => ! $errors->has($name),
        ]) }}
    >
    @error($name)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
