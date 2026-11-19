{{--
    The four steps of becoming a landlord. Reads `step` from the Alpine scope it sits in:
    on the sign-up form that is the form's own step (1 or 2); on the ID check page wrap it in x-data="{ step: 3 }".
    Done = coral check, current = coral ring, later = grey.
--}}
@php $steps = ['About you', 'Account', 'Verify ID and face', 'Admin review']; @endphp
        <ol class="mt-6 flex items-start" aria-label="Sign-up steps">
            @foreach($steps as $i => $name)
                @php $n = $i + 1; @endphp
                <li class="relative flex-1 flex flex-col items-center text-center" :aria-current="step === {{ $n }} ? 'step' : null">
                    @unless($loop->first)
                        <span class="absolute top-4 right-1/2 w-full h-0.5 -z-0 transition-colors"
                            :class="step >= {{ $n }} ? 'bg-[#FF8A66]' : 'bg-[#E2E4EC]'" aria-hidden="true"></span>
                    @endunless
                    <span class="relative z-10 w-8 h-8 rounded-full flex items-center justify-center text-[13px] font-bold border-2 transition-colors"
                        :class="step > {{ $n }} ? 'bg-[#FF8A66] border-[#FF8A66] text-[#060D26]' : (step === {{ $n }} ? 'bg-white border-[#FF8A66] text-[#060D26]' : 'bg-white border-[#E2E4EC] text-[#5B6A8E]')">
                        <template x-if="step > {{ $n }}">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                        </template>
                        <template x-if="step <= {{ $n }}"><span>{{ $n }}</span></template>
                    </span>
                    <span class="mt-1.5 text-[11.5px] leading-tight font-semibold px-1"
                        :class="step === {{ $n }} ? 'text-[#060D26]' : 'text-[#5B6A8E]'">{{ $name }}</span>
                </li>
            @endforeach
        </ol>
