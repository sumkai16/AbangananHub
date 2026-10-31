@props(['landlordName' => 'your landlord'])
{{--
    Plain-words safety tips shown under the QR and cash choices. AbangananHub doesn't hold this money, so these are
    the tenant's only protection besides the record we keep.
--}}
<div class="rounded-2xl border border-[#E2E4EC] bg-[#F7F8FC] px-4 py-3">
    <p class="text-[12.5px] font-bold text-[#060D26]">Stay safe when paying {{ $landlordName }} directly</p>
    <ul class="mt-1.5 list-disc space-y-1 pl-4 text-[12px] leading-relaxed text-[#5B6A8E]">
        <li>Pay only after you have seen the unit and signed the lease here.</li>
        <li>Pay only a QR shared in this chat, or hand cash to {{ $landlordName }} yourself. Never to someone else, and never outside this app.</li>
        <li>Keep your receipt and its reference number.</li>
        <li>If {{ $landlordName }} says they didn&rsquo;t get it, you can send your report to AbangananHub for review.</li>
    </ul>
</div>
