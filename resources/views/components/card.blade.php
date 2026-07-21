@props([
    'flush' => false,
])

{{--
    The standard surface for every panel in the app.
    Flat white over the #F7FCFC page background — replaced glassmorphism July 2026.
    Use `flush` when the card wraps a table or list that supplies its own padding.
--}}
<div {{ $attributes->class([
    'bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)]',
    'p-5 sm:p-6' => ! $flush,
    'overflow-hidden' => $flush,
]) }}>
    {{ $slot }}
</div>
