{{-- "Verified" pill on the listing photo (DESIGN.md §14). A property with verified ownership documents gets the
     "Verified Property" pill with an explaining popover; every other listed property gets a plain "Verified" pill.
     Used by the phone strip and the desktop bento. Props: $property, $class (position classes). --}}
@if($property->hasVerifiedDocuments())
    <div class="{{ $class }}" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" @click.stop>
        <button type="button" @click="open = !open"
            class="inline-flex items-center gap-1.5 bg-[#FF8A66] text-[#060D26] text-[11px] font-bold px-2.5 py-1.5 rounded-full shadow-sm hover:bg-[#E96F4F] transition-all">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Verified Property
        </button>

        <div x-show="open" x-cloak
             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
             class="absolute top-full left-0 mt-2 w-64 bg-white text-left rounded-xl shadow-lg border border-[#E2E4EC] p-3.5">
            <p class="font-bold text-[#060D26] text-[12px] mb-1">Verified Property</p>
            <p class="text-[#5B6A8E] text-[12px] leading-snug">Our team has reviewed and confirmed this property's ownership documents — title, tax declaration, or business permit.</p>
        </div>
    </div>
@else
    <span class="{{ $class }} inline-flex items-center gap-1.5 bg-[#FF8A66] text-[#060D26] text-[11px] font-bold px-2.5 py-1.5 rounded-full shadow-sm">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        Verified
    </span>
@endif
