{{-- Approve a landlord's GCash account. Coral when the name matches their ID, plain when it does not (a mismatch deserves a second look). --}}
<form method="POST" action="{{ route('admin.payouts.approveAccount', $landlord) }}"
    data-confirm="Approve this GCash account?"
    data-confirm-message="{{ $matches === false ? 'The name does not match their ID. Only approve if you have confirmed the account is theirs to use.' : 'Payouts to this landlord can then go out.' }}"
    data-confirm-button="Approve">
    @csrf
    <button type="submit" class="h-8 px-3 rounded-lg text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 cursor-pointer {{ $matches === false ? 'border border-[#E2E4EC] bg-white hover:bg-[#F7F8FC]' : 'bg-[#FF8A66] hover:bg-[#E96F4F]' }}">Approve account</button>
</form>
