@props(['action', 'value' => null, 'placeholder' => 'Search', 'clear' => null])
{{-- The search box on admin lists. It sits in the title row's actions, so a list does not spend a whole band on it. Hidden inputs go in the slot. --}}
<form data-live method="GET" action="{{ $action }}" role="search" {{ $attributes->class('flex items-center gap-2') }}>
    {{ $slot }}
    <div class="relative w-full sm:w-80">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-[#5B6A8E]" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z" /></svg>
        <input type="text" name="search" value="{{ $value }}" placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}"
            x-on:input.debounce.400ms="$el.form.requestSubmit()"
            class="h-9 w-full rounded-lg border border-[#E2E4EC] bg-white pl-8 pr-3 text-[13px] text-[#060D26] placeholder-[#5B6A8E] transition-colors duration-200 focus:border-[#FF8A66] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20">
    </div>
    @if($value && $clear)
        <a href="{{ $clear }}" class="inline-flex h-9 items-center rounded-lg px-2.5 text-[13px] font-semibold text-[#5B6A8E] transition-colors duration-200 hover:text-[#060D26]">Clear</a>
    @endif
</form>
