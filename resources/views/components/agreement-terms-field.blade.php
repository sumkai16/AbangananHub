@props([
    // Prefill, usually the property's standard terms. Alpine forms bind with x-model instead and leave this empty.
    'value' => '',
    'id' => 'agreement_terms_notes',
])

{{--
    The "Additional terms" box on the Send-agreement forms (chat panel and reservations modal). The text is
    frozen into the lease when the agreement is sent (Reservation::advanceToPendingAgreement), so what the
    landlord types here is what the tenant signs.
--}}
<div class="mb-3">
    <label for="{{ $id }}" class="block text-[11px] font-semibold text-[#060D26] mb-1">Additional terms <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
    <textarea id="{{ $id }}" name="agreement_terms_notes" rows="4" maxlength="2000"
        placeholder="Quiet hours, visitor policy, anything else the tenant should agree to"
        {{ $attributes->merge(['class' => 'w-full px-3 py-2 rounded-lg border border-[#E2E4EC] bg-white text-[12px] text-[#060D26] placeholder-[#5B6A8E]/70 leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/25 focus:border-[#FF8A66] transition-all duration-200']) }}>{{ $value }}</textarea>
    @error('agreement_terms_notes')<p class="text-[11px] text-[#DC2626] mt-1">{{ $message }}</p>@enderror
</div>
