@props([
    'title',
    // `note` is the new name; `message` is what the older pages pass. Same thing.
    'note' => null,
    'message' => null,
    // A button-link under the text (the older way). A newer page can put any buttons in the slot instead.
    'href' => null,
    'cta' => 'Get started',
    // A preset icon name (below), or give an `icon` slot with your own SVG. Anything unknown falls back to "inbox".
    'symbol' => 'inbox',
    // Sits inside an existing card: no box of its own. Without it the component draws its own dashed box (the original look).
    'flat' => false,
    // With `flat`: stretch to the free height of a flex-column parent (a card with `fill` on an `admin-fill` page) and centre in it.
    'fill' => false,
    // With `flat`: no icon, small padding, for a panel that sits inside a bigger page.
    'compact' => false,
])
{{--
    The one empty state for the whole app (admin, landlord, tenant). Say what is missing, say what to do about it, and
    put the next step in the slot when there is one. (No component tags in this comment: Blade compiles them even here.)

    Standalone, on the page background (the original look, used by browse, tenants and rentals): title, message,
    optional href + cta for a button, optional `icon` slot for your own SVG.

    Inside a card, centred in the free height of an admin page: wrap it in a card with `flush fill` on a page container
    with `admin-fill`, then pass `flat fill symbol="wallet" title="No payments here" note="..."`. Buttons for the next
    step go in the slot. `compact` drops the icon for a small panel inside a bigger page.

    Titles say what is absent ("No payments here"); notes say what to do next. Both sentence case, no full stop on the title.
--}}
@php
    $paths = [
        'inbox' => 'M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z',
        'wallet' => 'M21 12a2.25 2.25 0 0 0-2.25-2.25H15a3 3 0 1 1-6 0H5.25A2.25 2.25 0 0 0 3 12m18 0v6a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 9m18 0V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v3',
        'banknotes' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z',
        'search' => 'M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z',
        'document' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
        'users' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        'check' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    ];
    $text = $note ?? $message;
    $hasIconSlot = isset($icon) && ! $icon->isEmpty();
@endphp
@if ($flat)
    <div {{ $attributes->class([
        'flex flex-col items-center justify-center px-6 text-center',
        'min-h-[16rem] flex-1 py-16' => $fill,
        'py-12' => ! $fill && ! $compact,
        'py-6' => ! $fill && $compact,
    ]) }}>
        @unless ($compact)
            <span class="mb-4 grid h-12 w-12 place-items-center rounded-2xl bg-[#ECEEF6] text-[#5B6A8E]" aria-hidden="true">
                @if ($hasIconSlot)
                    {{ $icon }}
                @else
                    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $paths[$symbol] ?? $paths['inbox'] }}" /></svg>
                @endif
            </span>
        @endunless
        <p class="{{ $compact ? 'text-[13px] font-medium text-[#5B6A8E]' : 'text-[15px] font-semibold text-[#060D26]' }}">{{ $title }}</p>
        @if ($text)
            <p class="mt-1 max-w-sm text-[13px] leading-5 text-[#5B6A8E]">{{ $text }}</p>
        @endif
        @if ($href || ! $slot->isEmpty())
            <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                @if ($href)
                    <a href="{{ $href }}" class="inline-flex h-9 items-center rounded-lg bg-[#FF8A66] px-4 text-[13px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#E96F4F]">{{ $cta }}</a>
                @endif
                {{ $slot }}
            </div>
        @endif
    </div>
@else
    {{-- The original standalone look, unchanged, so the pages that already use it do not move. --}}
    <div class="text-center py-16 px-8 border-2 border-dashed border-[#E2E4EC] rounded-[24px] bg-[#F7F8FC]">
        <div class="w-16 h-16 rounded-full bg-white flex items-center justify-center mx-auto mb-4 text-[#94A3B8] shadow-sm border border-[#E2E4EC]">
            {{ $icon ?? '' }}
        </div>
        <div class="text-[16px] font-bold text-[#060D26] mb-2">{{ $title }}</div>
        <div class="text-[14px] text-[#5B6A8E] mb-6">{{ $text }}</div>
        @if($href)
            <a href="{{ $href }}" class="inline-flex items-center gap-2 px-6 py-3 bg-[#FF8A66] text-[#060D26] rounded-lg text-[15px] font-bold shadow-md hover:bg-[#E96F4F] transition-colors">
                {{ $cta }}
            </a>
        @endif
    </div>
@endif
