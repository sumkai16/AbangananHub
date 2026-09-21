{{--
    Detail for one row of the rent ledger: the month's figures plus the individual
    payments that made it up. Opened by dispatching `open-period-detail` with the
    array from RentLedger::periodPayload() (plus `pill` / `status_label` for the
    status chip, and `void_action` on a payment the landlord may void).

    Shared by the landlord and tenant tenancy pages — a payment without
    `void_action` simply renders without the Void button.

    Same two-flag shell as void-payment-modal / record-payment-modal: `show`
    drives visibility so the leave transition plays.
--}}
<div x-data="{
        show: false,
        period: null,
        open(detail) { this.period = detail; this.show = true; },
        close() { this.show = false; },
        peso(value) {
            return '₱' + (value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        voidPayment(p) {
            const detail = { id: p.id, action: p.void_action, amount: p.amount, label: this.period.label + ' Rent, recorded ' + p.date };
            this.close();
            this.$nextTick(() => window.dispatchEvent(new CustomEvent('open-void-payment', { detail })));
        },
     }"
     x-on:open-period-detail.window="open($event.detail)"
     x-on:keydown.escape.window="close()">

    <template x-teleport="body">
        <div x-show="show" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog"
            aria-modal="true" aria-labelledby="period-detail-title">

            {{-- Backdrop --}}
            <div x-show="show" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0" @click="close()"
                class="absolute inset-0 bg-[#060D26]/40 backdrop-blur-sm"></div>

            {{-- Panel --}}
            <div x-show="show"
                x-transition:enter="transition ease-[cubic-bezier(0.34,1.56,0.64,1)] duration-300"
                x-transition:enter-start="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-4 motion-reduce:scale-100 motion-reduce:translate-y-0"
                class="relative w-full max-w-md max-h-[calc(100vh-2rem)] flex flex-col rounded-2xl bg-white border border-[#E2E4EC] shadow-[0_4px_24px_rgba(0,0,0,0.12)]">

                <template x-if="period">
                    <div class="flex flex-col min-h-0">
                        {{-- Header --}}
                        <div class="flex items-start justify-between gap-3 px-5 sm:px-6 pt-5 pb-4 border-b border-[#E2E4EC]">
                            <div class="min-w-0">
                                <h2 id="period-detail-title" class="text-[17px] font-semibold text-[#060D26]" x-text="period.label + ' rent'"></h2>
                                <span class="mt-1.5 inline-flex items-center h-6 px-2.5 rounded-full border text-[11px] font-bold"
                                    :class="period.pill" x-text="period.status_label"></span>
                            </div>
                            <button type="button" @click="close()" aria-label="Close"
                                class="-mr-1.5 -mt-1 h-9 w-9 shrink-0 inline-flex items-center justify-center rounded-lg text-[#5B6A8E] hover:bg-[#ECEEF6] hover:text-[#060D26] transition-colors duration-200 cursor-pointer">
                                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div class="overflow-y-auto px-5 sm:px-6 py-5">
                            {{-- Figures --}}
                            <dl class="grid grid-cols-2 gap-x-6 gap-y-4">
                                <div>
                                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Due on</dt>
                                    <dd class="mt-1 text-[14px] font-semibold text-[#060D26]" x-text="period.due_on"></dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Expected</dt>
                                    <dd class="mt-1 text-[14px] font-semibold text-[#060D26] tabular-nums" x-text="peso(period.expected)"></dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Paid</dt>
                                    <dd class="mt-1 text-[14px] font-semibold text-[#060D26] tabular-nums" x-text="peso(period.paid)"></dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Balance</dt>
                                    {{-- Red is for money owed; a future month isn't owed yet. --}}
                                    <dd class="mt-1 text-[14px] font-semibold tabular-nums"
                                        :class="period.balance > 0 && !period.is_future ? 'text-[#DC2626]' : 'text-[#060D26]'"
                                        x-text="peso(period.balance)"></dd>
                                </div>
                            </dl>

                            {{-- Payments that made up this month --}}
                            <h3 class="mt-6 text-[13px] font-semibold text-[#060D26]">Payments received</h3>
                            <template x-if="period.payments.length === 0">
                                <p class="mt-2 rounded-xl bg-[#F7F8FC] px-4 py-3 text-[13px] text-[#5B6A8E]">No payment recorded for this month yet.</p>
                            </template>
                            <ul class="mt-2 divide-y divide-[#E2E4EC] rounded-xl border border-[#E2E4EC]" x-show="period.payments.length > 0">
                                <template x-for="p in period.payments" :key="p.id">
                                    <li class="flex items-start justify-between gap-3 px-4 py-3">
                                        <div class="min-w-0">
                                            <p class="text-[13.5px] font-semibold text-[#060D26] tabular-nums" x-text="peso(p.amount)"></p>
                                            <p class="mt-0.5 text-[12px] text-[#5B6A8E]">
                                                <span x-text="p.date"></span>
                                                <span> · </span><span x-text="p.method"></span>
                                                <template x-if="p.reference"><span> · Ref <span x-text="p.reference"></span></span></template>
                                            </p>
                                        </div>
                                        <button type="button" x-show="p.void_action" @click="voidPayment(p)"
                                            class="shrink-0 h-8 px-2 text-[12.5px] font-semibold text-[#5B6A8E] hover:text-[#DC2626] transition-colors duration-200 cursor-pointer">
                                            Void
                                        </button>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        <div class="px-5 sm:px-6 py-4 border-t border-[#E2E4EC] flex justify-end">
                            <button type="button" @click="close()"
                                class="h-10 px-5 rounded-xl border border-[#E2E4EC] text-[13px] font-semibold text-[#060D26] hover:border-[#060D26]/40 hover:bg-[#F7F8FC] transition-colors duration-200 cursor-pointer">
                                Close
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
