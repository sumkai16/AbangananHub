@props([
    'name',
    'class' => 'w-4 h-4',
])

{{-- Name-keyed icon per seeded amenity — see app/Support/AmenityIcons.php for
     the map and the fallback checkmark used for anything unmapped. --}}
<svg {{ $attributes->class([$class]) }} fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\AmenityIcons::path($name) }}" />
</svg>
