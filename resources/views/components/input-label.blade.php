@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-[#060D26]']) }}>
    {{ $value ?? $slot }}
</label>
