@props(['previewUrl', 'isPdf' => false, 'alt' => 'Document', 'height' => 'h-64'])

<div class="rounded-xl overflow-hidden border border-[#E2E8F0] bg-[#F8FAFC] relative" x-data="{ expanded: false, failed: false }">
    <button type="button" @click="expanded = true" x-show="!failed"
            class="absolute top-2 right-2 z-10 inline-flex items-center gap-1 h-7 px-2.5 rounded-full bg-white/95 border border-[#E2E8F0] text-[11px] font-medium text-[#1F2937] shadow-sm hover:bg-white transition-colors">
        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M20.25 3.75v4.5m0-4.5h-4.5m4.5 0L15 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15m11.25 5.25v-4.5m0 4.5h-4.5m4.5 0L15 15"/>
        </svg>
        Expand
    </button>

    <div x-show="failed" x-cloak class="w-full {{ $height }} flex flex-col items-center justify-center gap-2 text-[#94A3B8]">
        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M18 22.5H6a2.25 2.25 0 0 1-2.25-2.25V3.75A2.25 2.25 0 0 1 6 1.5h7.243a1.5 1.5 0 0 1 1.06.44l4.257 4.257a1.5 1.5 0 0 1 .44 1.06V20.25A2.25 2.25 0 0 1 18 22.5Zm-9-13.5h.008v.008H9V9Z" />
        </svg>
        <p class="text-[13px] font-medium">Preview unavailable</p>
    </div>

    @if($isPdf)
        <iframe src="{{ $previewUrl }}" class="w-full {{ $height }}" x-show="!failed" x-on:error="failed = true"></iframe>
    @else
        <div class="w-full {{ $height }} flex items-center justify-center" x-show="!failed">
            <img src="{{ $previewUrl }}" alt="{{ $alt }}" class="max-w-full max-h-full object-contain" x-on:error="failed = true">
        </div>
    @endif

    <div x-show="expanded" x-cloak
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         class="fixed inset-0 z-[999] bg-black/80 flex items-center justify-center p-4 sm:p-8"
         @click.self="expanded = false" @keydown.escape.window="expanded = false">
        <div class="bg-white rounded-2xl overflow-hidden w-full h-full max-w-5xl relative flex flex-col shadow-2xl">
            <button type="button" @click="expanded = false"
                    class="absolute top-3 right-3 z-10 w-9 h-9 rounded-full bg-white border border-[#E2E8F0] flex items-center justify-center shadow-sm hover:bg-[#F1F5F9] transition-colors">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
            @if($isPdf)
                <iframe src="{{ $previewUrl }}" class="w-full h-full flex-1"></iframe>
            @else
                <img src="{{ $previewUrl }}" alt="{{ $alt }}" class="w-full h-full object-contain">
            @endif
        </div>
    </div>
</div>
