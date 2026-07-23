{{--
    Verification step rail.

    Desktop: a sticky vertical list of all five steps, so the flow's shape stays
    visible instead of resetting on every screen. Mobile: collapses to the
    horizontal progress bar the wizard shipped with.

    Reads `step` from the surrounding verificationWizard() Alpine component —
    no extra state. Steps are numbered because the flow genuinely is a fixed
    sequence; the order is information the applicant needs.
--}}

@php
    $steps = [
        ['n' => 1, 'label' => 'ID type'],
        ['n' => 2, 'label' => 'Photograph your ID'],
        ['n' => 3, 'label' => 'Face check'],
        ['n' => 4, 'label' => 'Business details'],
        ['n' => 5, 'label' => 'Review and submit'],
    ];
@endphp

{{-- ── Mobile: horizontal bar ─────────────────────────── --}}
<div class="lg:hidden mb-7">
    <div class="flex items-center gap-3">
        <button type="button" @click="prevStep()" x-show="step > 1"
            class="p-1.5 -ml-1.5 rounded-lg text-[#64748B] hover:text-[#1F2937] hover:bg-[#EEF8F8] transition-colors">
            <span class="sr-only">Back to the previous step</span>
            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
        </button>
        <div class="flex gap-1.5 flex-1">
            <template x-for="i in 5" :key="i">
                <div class="h-1 flex-1 rounded-full transition-colors duration-300"
                    :class="step >= i ? 'bg-[#2AA7A1]' : 'bg-[#E2E8F0]'"></div>
            </template>
        </div>
        <span class="text-xs font-semibold text-[#64748B] whitespace-nowrap">Step <span x-text="step"></span> of 5</span>
    </div>
</div>

{{-- ── Desktop: vertical rail ─────────────────────────── --}}
<aside class="hidden lg:block lg:sticky lg:top-8">
    <p class="text-[11px] font-bold uppercase tracking-[0.11em] text-[#156F8C]">Become a landlord</p>
    <p class="mt-1 text-xs text-[#64748B] leading-relaxed">About 4 minutes. Your ID is stored privately and only an
        admin reviews it.</p>

    <ol class="mt-5 space-y-0.5">
        @foreach ($steps as $s)
            <li class="flex items-start gap-3 px-3 py-2.5 rounded-xl transition-colors duration-200"
                :class="step === {{ $s['n'] }} ? 'bg-white shadow-[0_1px_3px_rgba(15,23,42,0.06)]' : ''">
                <span
                    class="w-[22px] h-[22px] shrink-0 mt-px rounded-full border-[1.5px] flex items-center justify-center text-[11px] font-bold transition-colors duration-200"
                    :class="step > {{ $s['n'] }}
                        ? 'bg-[#22C55E] border-[#22C55E] text-white'
                        : (step === {{ $s['n'] }}
                            ? 'bg-[#2AA7A1] border-[#2AA7A1] text-white'
                            : 'bg-white border-[#E2E8F0] text-[#64748B]')">
                    <template x-if="step > {{ $s['n'] }}">
                        <svg class="w-2.5 h-2.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                            stroke-width="3.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </template>
                    <template x-if="step <= {{ $s['n'] }}">
                        <span>{{ $s['n'] }}</span>
                    </template>
                </span>
                <span class="min-w-0">
                    <span class="block text-[13px] leading-snug transition-colors duration-200"
                        :class="step >= {{ $s['n'] }} ? 'text-[#1F2937] font-semibold' : 'text-[#64748B] font-medium'">
                        {{ $s['label'] }}
                    </span>
                    @if ($s['n'] === 1)
                        <span x-show="step > 1 && idType" class="block text-[11.5px] text-[#64748B] truncate"
                            x-text="idType"></span>
                    @elseif ($s['n'] === 2)
                        <span x-show="step > 2" class="block text-[11.5px] text-[#64748B]"
                            x-text="needsBack ? 'Front and back' : 'Data page'"></span>
                    @elseif ($s['n'] === 3)
                        <span x-show="step > 3" class="block text-[11.5px] text-[#64748B]"
                            x-text="livenessPassed ? 'Liveness verified' : 'Captured'"></span>
                    @endif
                </span>
            </li>
        @endforeach
    </ol>

    <div class="mt-5 pt-4 border-t border-[#E2E8F0]">
        <p class="text-xs text-[#64748B] leading-relaxed">Rather do this on your phone?
            <button type="button"
                x-data="emailLinkButton('{{ route('landlord.verification.sendEmailLink') }}', '{{ csrf_token() }}')"
                @click="send()" class="text-left font-semibold text-[#1F2937] hover:text-[#156F8C] transition-colors"
                :class="done ? 'pointer-events-none' : ''">
                <span x-show="!sending && !done" class="underline">Send myself a link</span>
                <span x-show="sending" style="display:none">Sending...</span>
                <span x-show="done" style="display:none" :class="success ? 'text-[#22C55E]' : 'text-[#FBBF24]'"
                    x-text="message"></span>
            </button>
        </p>
    </div>
</aside>
