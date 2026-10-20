{{--
    Teleported modal shell for forms that hold an <x-datetime-picker>. The
    picker is ~600px tall, so it can't sit inline in a list or an action bar
    (see _move-in-clock); teleported to body, the backdrop scrolls instead.

    @props
      show      name of the Alpine boolean in the caller's scope
      title
      subtitle  optional
--}}
@props(['show', 'title', 'subtitle' => null])

<template x-teleport="body">
    <div x-show="{{ $show }}" x-cloak @keydown.escape.window="{{ $show }} = false"
        class="fixed inset-0 z-[200] overflow-y-auto bg-black/40 backdrop-blur-sm"
        x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0">
        <div class="min-h-full flex items-start sm:items-center justify-center p-3 sm:p-6">
            <div @click.outside="{{ $show }} = false" role="dialog" aria-modal="true" aria-label="{{ $title }}"
                class="w-full max-w-3xl rounded-2xl bg-white shadow-xl overflow-hidden">
                <div class="bg-[#F7F8FC] border-b border-[#E2E4EC] px-5 sm:px-6 py-4 flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-[15px] sm:text-[16px] font-bold text-[#060D26]">{{ $title }}</p>
                        @if ($subtitle)
                            <p class="mt-1 text-[12.5px] text-[#5B6A8E]">{{ $subtitle }}</p>
                        @endif
                    </div>
                    <button type="button" @click="{{ $show }} = false"
                        class="shrink-0 -mr-1 w-8 h-8 rounded-lg flex items-center justify-center text-[#5B6A8E] hover:bg-white transition-colors">
                        <span class="sr-only">Close</span>
                        <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                {{ $slot }}
            </div>
        </div>
    </div>
</template>
