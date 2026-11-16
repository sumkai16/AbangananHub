{{--
    A soft note (never blocks a save) when a low-rent unit asks for more than the Rent Control Act allows.
    Pass Alpine expressions: rent, deposit, and optionally advance (months). See config/rentals.php `rent_control`.
--}}
@php $rc = config('rentals.rent_control'); @endphp
<p x-cloak
    x-show="(Number({{ $rent }}) || 0) > 0 && (Number({{ $rent }}) || 0) <= {{ $rc['ceiling'] }}
        && ((Number({{ $deposit }}) || 0) > {{ $rc['max_deposit_months'] }} * Number({{ $rent }}){{ isset($advance) ? ' || (Number('.$advance.') || 0) > '.$rc['max_advance_months'] : '' }})"
    class="mt-1.5 rounded-lg border border-[#F59E0B]/40 bg-[#FFFBEB] px-3 py-2 text-[12px] leading-snug text-[#92400E]" role="note">
    Units at ₱{{ number_format($rc['ceiling']) }} or less may fall under the Rent Control Act (RA 9653): up to {{ $rc['max_advance_months'] }} month advance and {{ $rc['max_deposit_months'] }} months deposit. You can still save.
</p>
