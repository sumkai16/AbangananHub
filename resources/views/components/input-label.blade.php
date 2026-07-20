@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-[#1F2937]']) }}>
    {{ $value ?? $slot }}
</label>
