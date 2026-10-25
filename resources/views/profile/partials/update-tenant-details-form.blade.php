@php
    $detail = $user->tenantDetail;
@endphp

<div class="rounded-2xl ring-1 ring-[#5B6A8E]/10 shadow-[0_2px_12px_rgba(6,13,38,0.05)] bg-white p-4 sm:p-8">

    {{-- Section header --}}
    <div class="mb-5 sm:mb-7 border-b border-[#E2E4EC] pb-4 sm:pb-6 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-[#ECEEF6] flex items-center justify-center shrink-0">
            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
            </svg>
        </div>
        <div>
            <h2 class="text-[15px] font-normal text-[#060D26]">Emergency contact &amp; ID</h2>
            <p class="mt-0.5 text-sm text-[#5B6A8E]">Your landlord keeps these on file. Only the landlord of a place you have booked can see them.</p>
        </div>
    </div>

    <form method="post" action="{{ route('profile.tenantDetails.update') }}" novalidate>
        @csrf
        @method('patch')

        @php $missing = \App\Models\TenantDetail::missingFor($detail); @endphp
        @if ($missing === [])
            <p class="mb-5 rounded-lg bg-[#16A34A]/[0.08] px-3 py-2 text-[12.5px] font-medium text-[#15803D]">Complete. You can sign agreements without being asked again.</p>
        @else
            <p class="mb-5 rounded-lg bg-[#FBBF24]/[0.12] px-3 py-2 text-[12.5px] font-medium text-[#B45309]">
                Saving is optional here, but you need these before you can sign an agreement: {{ implode(', ', $missing) }}.
            </p>
        @endif

        <x-tenant-details-fields :detail="$detail" id-prefix="settings" />

        <p class="mt-4 text-[12px] text-[#5B6A8E]">Your ID number is stored encrypted. Leave a box empty to keep what is already saved.</p>

        <div class="mt-7 flex items-center gap-4 border-t border-[#E2E4EC] pt-6">
            <button type="submit"
                class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#FF8A66] px-5 text-[13px] font-semibold text-[#060D26] transition hover:bg-[#E96F4F] active:scale-[0.98]">
                Save details
            </button>
        </div>
    </form>
</div>
