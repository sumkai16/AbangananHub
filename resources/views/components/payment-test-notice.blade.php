{{-- Shown beside every PayMongo checkout while the app runs on test keys
     (config('services.paymongo.test_mode')), so a visitor never thinks a
     real payment was taken. Renders nothing on live keys. --}}
@if(config('services.paymongo.test_mode'))
    <p {{ $attributes->merge(['class' => 'flex items-start gap-2 rounded-lg bg-[#FBBF24]/[0.10] ring-1 ring-[#FBBF24]/35 px-3 py-2 text-xs text-[#B45309]']) }}
       role="note">
        <svg class="w-4 h-4 shrink-0 mt-px" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008M10.34 3.94 1.82 18a1.875 1.875 0 0 0 1.6 2.81h17.16a1.875 1.875 0 0 0 1.6-2.81L13.66 3.94a1.875 1.875 0 0 0-3.32 0Z" />
        </svg>
        <span><strong class="font-semibold">Test mode.</strong> No real money is charged. Use PayMongo's test GCash flow to complete the payment.</span>
    </p>
@endif
