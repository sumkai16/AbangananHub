{{--
    "Share payment QR" (landlord only): pick a screenshot of the QR from GCash, Maya, GoTyme or a bank app.
    The browser reads it (jsQR) and sends only the decoded text; the server checks it is a real QR Ph code
    and redraws it. No image is uploaded or kept. Behaviour lives in paymentQrSheet() in
    conversations/index.blade.php (injected panels don't run their own scripts). Needs $conversation.
--}}
@php
    // The landlord's most recent QR in any chat. Offered first so they don't re-pick the screenshot for every tenant.
    $lastShare = \App\Models\PaymentQrShare::where('shared_by', auth()->id())->latest('payment_qr_share_id')->first();
@endphp
<div x-data="paymentQrSheet({{ $conversation->conversation_id }}, {{ $lastShare?->payment_qr_share_id ?? 'null' }})" :data-sheet-open="open" @keydown.escape.window="close()">
    <button type="button" @click="open = true"
        class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-xl border border-[#E2E4EC] bg-white px-3 text-[12px] font-bold text-[#060D26] transition-colors hover:bg-[#F7F8FC]">
        <svg class="h-4 w-4 text-[#B35A3D]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 3.75 9.375v-4.5ZM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 0 1-1.125-1.125v-4.5ZM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0 1 13.5 9.375v-4.5Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75ZM6.75 16.5h.75v.75h-.75v-.75ZM16.5 6.75h.75v.75h-.75v-.75ZM13.5 13.5h.75v.75h-.75v-.75ZM13.5 19.5h.75v.75h-.75v-.75ZM19.5 13.5h.75v.75h-.75v-.75ZM19.5 19.5h.75v.75h-.75v-.75ZM16.5 16.5h.75v.75h-.75v-.75Z"/></svg>
        <span class="hidden sm:inline">Share payment QR</span><span class="sm:hidden">QR</span>
    </button>

    <div x-show="open" x-cloak x-transition.opacity
        class="fixed inset-0 z-[200] flex items-end justify-center bg-black/50 sm:items-center sm:p-4" @click.self="close()"
        role="dialog" aria-modal="true" aria-labelledby="payment-qr-title">
        <form @submit.prevent="submit()"
            class="max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:max-w-md sm:rounded-2xl">
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h2 id="payment-qr-title" class="text-lg font-normal text-[#060D26]">Share your payment QR</h2>
                    <p class="mt-0.5 text-[13px] text-[#5B6A8E]">Your tenant pays this QR from their own app, then tells you. You confirm it. AbangananHub never holds the money.</p>
                </div>
                <button type="button" @click="close()" aria-label="Close"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[#5B6A8E] transition-colors hover:bg-[#ECEEF6]">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            @if($lastShare)
                <div class="mb-4" x-show="reuseId" x-cloak>
                    <p class="mb-2 text-[12px] font-semibold text-[#060D26]">Your QR from last time</p>
                    <div class="flex items-center gap-3 rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] p-3">
                        <div class="h-20 w-20 shrink-0 rounded-lg bg-white p-1 [&_svg]:h-full [&_svg]:w-full">{!! $lastShare->svg() !!}</div>
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-[#5B6A8E]">Pay to</p>
                            <p class="truncate text-[14px] font-bold text-[#060D26]">{{ $lastShare->account_name }}</p>
                            <button type="button" @click="reuseId = null" class="mt-1 text-[12px] font-bold text-[#B35A3D] underline underline-offset-2">Use a different QR</button>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mb-4" x-show="!reuseId">
                <p class="mb-2 text-[12px] font-semibold text-[#060D26]">Screenshot of your QR</p>
                <label for="payment-qr-file"
                    class="flex min-h-[7rem] cursor-pointer flex-col items-center justify-center gap-1.5 rounded-xl border border-dashed border-[#5B6A8E]/40 p-3 text-center text-[12px] text-[#5B6A8E] transition-colors hover:border-[#FF8A66] hover:text-[#060D26]">
                    <template x-if="preview"><img :src="preview" alt="Your QR" class="max-h-40 rounded-lg"></template>
                    <span x-show="!preview">Choose a picture of your QR (GCash &ldquo;QR&rdquo;, Maya &ldquo;Receive money&rdquo;, GoTyme or bank app)</span>
                    <span x-show="preview" x-cloak class="font-semibold text-[#15803D]">QR found. Tap to choose another.</span>
                </label>
                <input id="payment-qr-file" type="file" accept="image/*" class="sr-only" @change="pick($event)">
                <p x-show="errors.payload" x-text="errors.payload" class="mt-1.5 text-[12px] text-[#EF4444]"></p>
            </div>

            <div class="mb-5">
                <label for="payment-qr-note" class="mb-2 block text-[12px] font-semibold text-[#060D26]">Note <span class="font-normal text-[#5B6A8E]">(optional)</span></label>
                <input id="payment-qr-note" x-model="note" maxlength="300" placeholder="e.g. Please pay on or before the 5th."
                    class="w-full rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] px-3.5 py-2.5 text-[14px] text-[#060D26] placeholder-[#94A3B8] focus:border-[#FF8A66] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20">
                <p x-show="errors.note" x-text="errors.note" class="mt-1.5 text-[12px] text-[#EF4444]"></p>
            </div>

            <p x-show="errors.general" x-text="errors.general" class="mb-3 text-[12px] text-[#EF4444]"></p>

            <button type="submit" :disabled="busy || (!payload && !reuseId)"
                class="h-11 w-full rounded-xl bg-[#FF8A66] text-[14px] font-bold text-[#060D26] transition-colors hover:bg-[#E96F4F] disabled:opacity-60">
                <span x-show="!busy">Share with {{ $conversation->tenant?->first_name ?? 'tenant' }}</span><span x-show="busy" x-cloak>Sharing…</span>
            </button>
        </form>
    </div>
</div>
