{{--
    The final terms on a Send-agreement form (chat panel and the reservations list's modal). Bound to an Alpine
    `agreement` object in the parent scope, filled from Reservation::agreementDefaults(): rent, deposit, advance,
    move_in, move_out, due_day, occupants, limit, terms. SendAgreementRequest validates; the move-in amount the tenant
    will pay is shown live (advance × rent + deposit, Reservation::moveInDue()).
    Optional: $idPrefix to keep ids unique when two forms are on one page.
--}}
@php
    $p = $idPrefix ?? 'agreement';
    $input = 'h-10 w-full rounded-lg border border-[#E2E4EC] bg-white px-3 text-[13px] text-[#060D26] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/25 focus:border-[#FF8A66] transition-all duration-200';
    $label = 'block text-[11px] font-semibold text-[#060D26] mb-1';
@endphp

<div class="grid gap-3 mb-3" x-data="{
        monthAfter(d) {
            if (!d) return null;
            const x = new Date(d + 'T00:00:00');
            x.setMonth(x.getMonth() + 1);
            return x.getFullYear() + '-' + String(x.getMonth() + 1).padStart(2, '0') + '-' + String(x.getDate()).padStart(2, '0');
        },
        get moveInDue() {
            return (Number(agreement.advance) || 0) * (Number(agreement.rent) || 0) + (Number(agreement.deposit) || 0);
        },
    }">
    <div class="grid grid-cols-2 gap-2.5">
        <div>
            <label for="{{ $p }}_rent" class="{{ $label }}">Monthly rent (₱)</label>
            <input id="{{ $p }}_rent" type="number" name="agreed_monthly_rent" x-model="agreement.rent" min="0" step="0.01" required class="{{ $input }}">
        </div>
        <div>
            <label for="{{ $p }}_deposit" class="{{ $label }}">Security deposit (₱)</label>
            <input id="{{ $p }}_deposit" type="number" name="security_deposit" x-model="agreement.deposit" min="0" step="0.01" class="{{ $input }}">
        </div>
        <div>
            <label for="{{ $p }}_advance" class="{{ $label }}">Advance rent</label>
            <x-styled-select name="advance_months" :options="['0' => 'None', '1' => '1 month', '2' => '2 months', '3' => '3 months']"
                x-model="agreement.advance" class="{{ $input }} text-left" />
        </div>
        <div>
            <label for="{{ $p }}_due" class="{{ $label }}">Rent due day</label>
            <input id="{{ $p }}_due" type="number" name="rent_due_day" x-model="agreement.due_day" min="1" max="28" placeholder="Move-in day" class="{{ $input }}">
        </div>
        <div>
            <label for="{{ $p }}_move_in" class="{{ $label }}">Move-in</label>
            <x-date-picker name="target_move_in_date" id="{{ $p }}_move_in" x-model="agreement.move_in" :min="now()->toDateString()" />
        </div>
        <div>
            <label for="{{ $p }}_move_out" class="{{ $label }}">Move-out <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
            <x-date-picker name="target_move_out_date" id="{{ $p }}_move_out" x-model="agreement.move_out" min-expr="monthAfter(agreement.move_in)" placeholder="Open-ended" />
        </div>
        <div class="col-span-2">
            <label for="{{ $p }}_occupants" class="{{ $label }}">Occupants</label>
            <input id="{{ $p }}_occupants" type="number" name="occupants_count" x-model="agreement.occupants" min="1" :max="agreement.limit || 20" class="{{ $input }}">
        </div>
    </div>

    <p class="rounded-lg bg-white px-3 py-2 text-[12px] text-[#060D26]">
        Due at move-in, paid to you:
        <span class="font-semibold tabular-nums" x-text="'₱' + moveInDue.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
        <span class="text-[#5B6A8E]" x-text="'(' + (Number(agreement.advance) || 0) + ' month' + (Number(agreement.advance) == 1 ? '' : 's') + ' advance + deposit)'"></span>
    </p>

    <x-agreement-terms-field id="{{ $p }}_terms" x-model="agreement.terms" />
</div>
