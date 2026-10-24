{{--
    A shared payment QR: the code drawn from the validated text, who it pays, the landlord's note, and
    buttons to enlarge it or save it as a picture (a phone cannot scan its own screen; the tenant uploads
    the saved image in their wallet app). Needs $share (PaymentQrShare). Optional $compact.
--}}
<div x-data="{ big: false }" class="min-w-0">
    <div class="flex items-start gap-3">
        <button type="button" @click="big = true" aria-label="Enlarge the payment QR"
            class="shrink-0 rounded-xl border border-[#E2E4EC] bg-white p-1.5 hover:border-[#FF8A66] transition-colors">
            <span data-qr class="block {{ ($compact ?? false) ? 'w-24' : 'w-32' }} [&>svg]:h-auto [&>svg]:w-full">{!! $share->svg() !!}</span>
        </button>
        <div class="min-w-0 flex-1">
            <p class="text-[10.5px] font-bold uppercase tracking-wider text-[#5B6A8E]">Pay to</p>
            <p class="text-[14px] font-bold text-[#060D26] break-words">{{ $share->account_name }}</p>
            @if($share->note)
                <p class="mt-1 text-[12px] text-[#060D26] whitespace-pre-wrap break-words">{{ $share->note }}</p>
            @endif
            <p class="mt-1 text-[11px] text-[#5B6A8E]">Shared {{ $share->created_at->format('M j') }}. Works with GCash, Maya, GoTyme and bank apps.</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <button type="button" @click="big = true"
                    class="h-8 rounded-lg border border-[#E2E4EC] px-3 text-[11px] font-bold text-[#060D26] hover:bg-[#F7F8FC] transition-colors">Enlarge</button>
                <button type="button" onclick="downloadQr(this)"
                    class="h-8 rounded-lg border border-[#E2E4EC] px-3 text-[11px] font-bold text-[#060D26] hover:bg-[#F7F8FC] transition-colors">Save image</button>
            </div>
        </div>
    </div>

    <template x-teleport="body">
        <div x-show="big" x-cloak @keydown.escape.window="big = false" @click.self="big = false"
            class="fixed inset-0 z-[80] flex items-center justify-center bg-[#060D26]/70 p-4" role="dialog" aria-modal="true" aria-label="Payment QR">
            <div class="w-full max-w-[360px] rounded-2xl bg-white p-5 text-center shadow-xl">
                <p class="text-[13px] font-bold text-[#060D26]">{{ $share->account_name }}</p>
                <div data-qr class="mx-auto mt-3 w-full [&>svg]:h-auto [&>svg]:w-full">{!! $share->svg(400) !!}</div>
                <p class="mt-3 text-[11.5px] text-[#5B6A8E]">Scan with your wallet or bank app, or use "Save image" and upload it there.</p>
                <button type="button" @click="big = false"
                    class="mt-4 h-10 w-full rounded-xl bg-[#FF8A66] text-[13px] font-bold text-[#060D26] hover:bg-[#E96F4F] transition-colors">Close</button>
            </div>
        </div>
    </template>
</div>
