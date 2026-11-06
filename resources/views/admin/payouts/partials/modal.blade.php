{{-- Mark-paid-out modal: records a transfer the admin sent by hand. The raw total it shows is posted back so a payout is only recorded for the amount that was actually sent. --}}
    <template x-teleport="body">
        <div x-data="{ open: false, userId: null, name: '', amount: '', rawAmount: '' }"
            x-on:open-payout-modal.window="open = true; userId = $event.detail.userId; name = $event.detail.name; amount = $event.detail.amount; rawAmount = $event.detail.rawAmount"
            x-show="open" x-cloak
            class="fixed inset-0 z-[200] flex items-center justify-center bg-black/40 px-4">
            <div x-show="open" x-on:click.outside="open = false" class="bg-white rounded-2xl shadow-xl max-w-sm w-full p-6">
                <h3 class="text-[15px] font-semibold text-[#060D26] mb-1">Mark payout sent</h3>
                <p class="text-[13px] text-[#5B6A8E] mb-4">
                    Confirm you have sent <span x-text="'₱' + amount"></span> via GCash to
                    <span x-text="name" class="font-semibold"></span>, then record the reference below.
                </p>
                <form method="POST" x-bind:action="'/admin/payouts/' + userId + '/mark-paid-out'">
                    @csrf
                    <input type="hidden" name="expected_total" x-bind:value="rawAmount">
                    <label class="block text-[13px] font-semibold text-[#060D26] mb-1.5">GCash transaction reference</label>
                    <input type="text" name="payout_reference" required
                        class="w-full px-3.5 py-2.5 bg-white border border-[#E2E4EC] rounded-lg text-[14px] text-[#060D26] focus:border-[#FF8A66] focus:ring-2 focus:ring-[#FF8A66]/20 focus:outline-none transition-all mb-4"
                        placeholder="e.g. 0123456789012">
                    <div class="flex items-center justify-end gap-3">
                        <button type="button" x-on:click="open = false"
                            class="px-4 py-2 border border-[#E2E4EC] rounded-lg text-[13px] font-semibold text-[#060D26] bg-white hover:brightness-95 transition-all">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 bg-[#FF8A66] text-[#060D26] rounded-lg text-[13px] font-semibold hover:bg-[#E96F4F] transition-all shadow-sm">
                            Confirm payout
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

