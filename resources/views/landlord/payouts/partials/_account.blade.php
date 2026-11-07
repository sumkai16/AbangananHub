{{-- Where payouts go: the landlord's GCash. Shown on the Payouts tab (next to the money it receives) and on
     Settings > Payments (next to the payment methods), one form for both. Saved by Landlord\ProfileController::update
     (password check included); $returnTo ('payouts' | 'settings', default payouts) brings the landlord back to the page
     they were on. No GCash yet: the form is open. Saved: one summary line with Change. Whether tenants may pay
     online is a payment method now (Settings > Payments), so this form no longer carries it. --}}
@php
    $returnTo = $returnTo ?? 'payouts';
    $account = $user->payoutAccount;
    $savedNumber = (string) $account?->gcash_number;
    $savedName = (string) $account?->gcash_account_name;
    $hasDestination = $savedNumber !== '' && $savedName !== '';
    $acceptsOnline = (bool) ($account?->accepts_online ?? true);
    $formErrors = $errors->hasAny(['gcash_number', 'gcash_account_name', 'current_password']);
    // 09171234567 reads as 0917 123 4567.
    $shownNumber = strlen($savedNumber) === 11 ? substr($savedNumber, 0, 4).' '.substr($savedNumber, 4, 3).' '.substr($savedNumber, 7) : $savedNumber;
    $input = 'w-full h-11 px-3.5 bg-white border border-[#E2E4EC] rounded-lg text-[16px] sm:text-[14px] text-[#060D26] placeholder-[#5B6A8E]/60 focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/20 focus:outline-none transition-colors';
@endphp

{{-- x-data sits on an inner div (this used to be an x-card, whose tag attributes do not compile @js()). --}}
{{-- On the Payouts tab it is its own card; inside Settings > Payments it is the second half of that panel. --}}
<div id="payout-account" class="scroll-mt-24 {{ $returnTo === 'settings' ? 'mt-6 border-t border-[#E2E4EC] pt-6' : 'mb-6 rounded-2xl border border-[#E2E4EC] bg-white p-5 shadow-[0_1px_3px_rgba(6,13,38,0.06)] sm:p-6' }}">
<div x-data="{
        editing: @js(! $hasDestination || $formErrors),
        savedNumber: @js($savedNumber), savedName: @js($savedName),
        number: @js(old('gcash_number', $savedNumber)), name: @js(old('gcash_account_name', $savedName)),
        get changing() { return (this.savedNumber !== '' || this.savedName !== '') && (this.number.trim() !== this.savedNumber || this.name.trim() !== this.savedName); },
    }">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h2 class="text-[15px] font-semibold text-[#060D26]">Where your payouts go</h2>
            @if ($hasDestination)
                <p class="mt-1 text-[14px] text-[#060D26]">
                    <span class="font-semibold tabular-nums">GCash {{ $shownNumber }}</span>
                    <span class="text-[#5B6A8E]">· {{ $savedName }}</span>
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-[12px]">
                    {{-- Not approved yet is the page's notice above (PayoutController::holdNotice), not said twice. --}}
                    @if ($account->isApproved())
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-[#22C55E]/10 px-2.5 py-1 font-semibold text-[#15803D]">
                            <span class="h-1.5 w-1.5 rounded-full bg-[#22C55E]"></span>Approved
                        </span>
                    @endif
                    <span class="text-[#5B6A8E]">{{ $acceptsOnline ? 'Tenants can pay you online' : 'Online payment turned off: tenants pay you directly' }}</span>
                    @if ($returnTo === 'payouts')
                        <a href="{{ route('profile.edit') }}#payments" class="font-semibold text-[#B35A3D] hover:underline">Payment methods</a>
                    @endif
                </div>
            @else
                <p class="mt-1 max-w-[65ch] text-[13px] leading-relaxed text-[#5B6A8E]">
                    Add your GCash so AbangananHub can send you the rent and deposits tenants pay online. Until both fields are filled in, tenants can only pay you directly.
                </p>
            @endif
        </div>
        @if ($hasDestination)
            <button type="button" x-show="! editing" @click="editing = true; $nextTick(() => $refs.number.focus())"
                class="inline-flex h-11 shrink-0 items-center justify-center rounded-full border border-[#E2E4EC] bg-white px-5 text-[13.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/40 cursor-pointer">
                Change
            </button>
        @endif
    </div>

    <form method="POST" action="{{ route('landlord.profile.update') }}" x-show="editing" @if ($hasDestination && ! $formErrors) x-cloak @endif
        class="mt-5 border-t border-[#E2E4EC] pt-5">
        @csrf
        @method('PATCH')
        <input type="hidden" name="return_to" value="{{ $returnTo }}">

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="gcash_number" class="mb-1.5 block text-[13px] font-semibold text-[#060D26]">GCash number</label>
                <input type="text" id="gcash_number" name="gcash_number" x-ref="number" x-model="number" value="{{ old('gcash_number', $savedNumber) }}"
                    inputmode="tel" autocomplete="off" placeholder="09XX XXX XXXX" class="{{ $input }} tabular-nums"
                    @error('gcash_number') aria-invalid="true" aria-describedby="gcash_number_error" @enderror>
                @error('gcash_number')
                    <p id="gcash_number_error" class="mt-1.5 text-[12px] font-semibold text-[#DC2626]">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="gcash_account_name" class="mb-1.5 block text-[13px] font-semibold text-[#060D26]">Name on the GCash account</label>
                <input type="text" id="gcash_account_name" name="gcash_account_name" x-model="name" value="{{ old('gcash_account_name', $savedName) }}"
                    autocomplete="name" placeholder="As shown in your GCash app" class="{{ $input }}"
                    @error('gcash_account_name') aria-invalid="true" aria-describedby="gcash_account_name_error" @enderror>
                @error('gcash_account_name')
                    <p id="gcash_account_name_error" class="mt-1.5 text-[12px] font-semibold text-[#DC2626]">{{ $message }}</p>
                @enderror
            </div>
        </div>
        <p class="mt-2 text-[12px] leading-relaxed text-[#5B6A8E]">Use your own GCash: the name should match your verified ID. An administrator approves the account before your first payout.</p>

        {{-- Changing where the money goes needs the password, so a borrowed session cannot redirect it. --}}
        <div x-show="changing || @js($errors->has('current_password'))" x-cloak class="mt-4">
            <label for="current_password" class="mb-1.5 block text-[13px] font-semibold text-[#060D26]">Your password</label>
            <input type="password" id="current_password" name="current_password" autocomplete="current-password"
                class="{{ $input }} sm:max-w-sm" @error('current_password') aria-invalid="true" aria-describedby="current_password_error" @enderror>
            <p class="mt-1.5 text-[12px] leading-relaxed text-[#5B6A8E]">Needed to change your GCash details. Payouts pause for 48 hours afterwards as a safety check, and we email you.</p>
            @error('current_password')
                <p id="current_password_error" class="mt-1.5 text-[12px] font-semibold text-[#DC2626]">{{ $message }}</p>
            @enderror
        </div>

        <div class="mt-5 flex flex-col-reverse gap-2.5 sm:flex-row sm:justify-end">
            @if ($hasDestination)
                <button type="button" @click="editing = false; number = savedNumber; name = savedName"
                    class="inline-flex h-11 items-center justify-center rounded-full px-5 text-[13.5px] font-semibold text-[#5B6A8E] transition-colors duration-200 hover:text-[#060D26] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]/40 cursor-pointer">
                    Cancel
                </button>
            @endif
            <button type="submit"
                class="inline-flex h-11 items-center justify-center rounded-full bg-[#FF8A66] px-6 text-[14px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#E96F4F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#060D26]/30 cursor-pointer">
                Save payout details
            </button>
        </div>
    </form>
</div>
</div>
