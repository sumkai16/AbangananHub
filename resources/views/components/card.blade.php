@props([
    'flush' => false,
    // Grow to the free height of a flex-column parent (a page with `admin-fill`), so a short table still fills the screen.
    'fill' => false,
])

{{--
    The standard surface for every panel in the app.
    Flat white over the #F7F8FC page background — replaced glassmorphism July 2026.
    Use `flush` when the card wraps a table or list that supplies its own padding.
--}}
<div {{ $attributes->class([
    'bg-white border border-[#E2E4EC] rounded-2xl shadow-[0_1px_3px_rgba(6,13,38,0.06)]',
    'card-pad p-5 sm:p-6' => ! $flush,
    'overflow-hidden' => $flush,
    'flex flex-1 flex-col' => $fill,
]) }}>
    {{ $slot }}
</div>
