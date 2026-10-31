@props(['n', 'title', 'gist' => null, 'accordion' => false, 'heading', 'open' => false, 'items' => null, 'body' => ''])
{{--
    One numbered lease clause. Give it `items` (the clause's sentences) and it renders the same words two ways:
    a bullet list on the agreement page, where people scan, and the original paragraph everywhere else and on paper.
    One source for the wording, so the two can never drift. A clause with one sentence, or free text passed as the
    slot, stays a paragraph.

    On the agreement page ($accordion) each clause is also a row you can open: title and a one-line gist when closed,
    the text when open. A closed <details> prints nothing, so the accordion is screen-only and a plain copy is printed
    instead: the printed lease always carries every clause in full.
--}}
@php
    $asList = $items !== null && count($items) > 1;
    $paragraph = $items !== null ? implode(' ', $items) : null;
@endphp
@if (! $accordion)
    <h3 class="{{ $heading }}">{{ $n }}. {{ $title }}</h3>
    @if ($items !== null)
        <p class="{{ $body }}">{{ $paragraph }}</p>
    @else
        {{ $slot }}
    @endif
@else
    <details data-clause class="group border-t border-[#E2E4EC] first:border-t-0 print:hidden" @if ($open) open @endif>
        <summary class="flex min-h-[44px] cursor-pointer list-none items-start justify-between gap-4 py-4 [&::-webkit-details-marker]:hidden">
            <span class="min-w-0">
                <span class="block text-[16.5px] font-semibold text-[#060D26]">{{ $n }}. {{ $title }}</span>
                @if ($gist)
                    <span class="mt-0.5 block text-[14px] text-[#5B6A8E] group-open:hidden">{{ $gist }}</span>
                @endif
            </span>
            <svg class="mt-1.5 h-5 w-5 shrink-0 text-[#5B6A8E] transition-transform duration-200 group-open:rotate-180 motion-reduce:transition-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
        </summary>
        <div class="-mt-1 pb-5">
            @if ($asList)
                <ul class="list-disc space-y-2 pl-5 marker:text-[#FF8A66] {{ $body }}">
                    @foreach ($items as $item)
                        <li class="pl-1">{{ $item }}</li>
                    @endforeach
                </ul>
            @elseif ($items !== null)
                <p class="{{ $body }}">{{ $paragraph }}</p>
            @else
                {{ $slot }}
            @endif
        </div>
    </details>
    <div class="hidden print:block">
        <h3 class="{{ $heading }}">{{ $n }}. {{ $title }}</h3>
        @if ($items !== null)
            <p class="{{ $body }}">{{ $paragraph }}</p>
        @else
            {{ $slot }}
        @endif
    </div>
@endif
