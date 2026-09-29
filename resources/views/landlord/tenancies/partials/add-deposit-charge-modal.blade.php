@php
    $depositModalInput = 'h-11 w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 text-[13.5px] text-[#060D26] placeholder-[#5B6A8E] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30 transition';
    $depositModalLabel = 'block text-[12px] font-semibold text-[#060D26] mb-1.5';
    $depositChargeFields = ['amount', 'category', 'description', 'charged_at'];
@endphp

{{--
    Two flags, not one: `show` drives visibility so the leave transition
    actually plays (x-if alone doesn't animate) — RULES.md → Modals & Overlays.
--}}
<div x-data="{
        show: {{ $errors->hasAny($depositChargeFields) ? 'true' : 'false' }},
        remaining: {{ (float) $summary['depositRemaining'] }},
     }"
     x-on:open-add-deposit-charge.window="show = true"
     x-on:keydown.escape.window="show = false">

    <template x-teleport="body">
        <div x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog"
            aria-modal="true" aria-labelledby="add-deposit-charge-title">

            {{-- Backdrop --}}
            <div x-show="show" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0" @click="show = false"
                class="absolute inset-0 bg-[#060D26]/40 backdrop-blur-sm"></div>

            {{-- Panel --}}
            <div x-show="show"
                x-transition:enter="transition ease-[cubic-bezier(0.34,1.56,0.64,1)] duration-300"
                x-transition:enter-start="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0"
                class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto bg-white border border-[#E2E4EC] rounded-2xl shadow-[0_20px_60px_rgba(6,13,38,0.18)]">

                <div class="flex items-start justify-between gap-4 px-6 pt-6 pb-4">
                    <div>
                        <h2 id="add-deposit-charge-title" class="text-[17px] font-normal text-[#060D26]">Add a deposit charge</h2>
                        <p class="text-[12.5px] text-[#5B6A8E] mt-0.5">
                            ₱{{ number_format($summary['depositRemaining'], 2) }} of the held deposit is available to charge.
                        </p>
                    </div>
                    <button type="button" @click="show = false" aria-label="Close"
                        class="w-8 h-8 shrink-0 rounded-lg flex items-center justify-center text-[#5B6A8E] hover:bg-[#F7F8FC] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('landlord.depositCharges.store', $reservation) }}" class="px-6 pb-6">
                    @csrf

                    <div class="grid sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="deposit_category" class="{{ $depositModalLabel }}">
                                Category <span class="text-[#EF4444]">*</span>
                            </label>
                            <x-styled-select name="category"
                                :options="$depositCategoryLabels" selected="Damage"
                                class="{{ $depositModalInput }} bg-white" />
                            @error('category')
                                <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="deposit_amount" class="{{ $depositModalLabel }}">
                                Amount (₱) <span class="text-[#EF4444]">*</span>
                            </label>
                            <input type="number" id="deposit_amount" name="amount" min="1" :max="remaining"
                                step="0.01" required placeholder="0.00" value="{{ old('amount') }}" class="{{ $depositModalInput }}">
                            @error('amount')
                                <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="deposit_description" class="{{ $depositModalLabel }}">
                            What happened <span class="text-[#EF4444]">*</span>
                        </label>
                        <textarea id="deposit_description" name="description" rows="3" maxlength="500" required
                            placeholder="Describe the damage, cleaning, or other reason for this charge — the tenant will see this."
                            class="w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 py-2.5 text-[13.5px] text-[#060D26] placeholder-[#5B6A8E] focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/30 transition resize-y">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-5">
                        <label for="deposit_charged_at" class="{{ $depositModalLabel }}">
                            Date
                        </label>
                        <input type="date" id="deposit_charged_at" name="charged_at" value="{{ old('charged_at', now()->toDateString()) }}"
                            max="{{ now()->toDateString() }}" class="{{ $depositModalInput }} cursor-pointer">
                        @error('charged_at')
                            <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-start gap-2.5 rounded-xl bg-[#F7F8FC] border border-[#E2E4EC] px-3.5 py-3 mb-5">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#5B6A8E" stroke-width="2"
                            class="shrink-0 mt-0.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                        <p class="text-[12px] text-[#5B6A8E] leading-relaxed">
                            The tenant is notified immediately. This can't charge more than what's still held on the
                            deposit — if you enter something wrong, you can void it afterwards.
                        </p>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5">
                        <button type="button" @click="show = false"
                            class="h-11 px-5 rounded-full border border-[#E2E4EC] text-[#5B6A8E] text-sm font-semibold hover:bg-[#F7F8FC] hover:text-[#060D26] transition-all duration-200 cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                            class="h-11 px-6 rounded-full bg-[#FF8A66] text-[#060D26] text-sm font-semibold hover:bg-[#E96F4F] transition-all duration-200 cursor-pointer">
                            Add charge
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>
</div>
