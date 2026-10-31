@props(['route', 'landlordName' => 'your landlord', 'moveIn' => true, 'fee' => 0, 'id' => 'agree'])
{{--
    The tick-box a tenant must check before paying or reporting a payment. The wording follows the route:
    'online' (PayMongo checkout), 'qr' (report a QR payment) or 'cash' (report cash). Put inside the form; the form
    needs x-data="{ agreed: false }" and its submit button :disabled="!agreed" so the button stays off until ticked.
    The server checks it too (App\Support\PaymentTerms).
--}}
<div class="rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] px-3 py-3">
    <label for="{{ $id }}" class="flex items-start gap-2.5 cursor-pointer">
        <input id="{{ $id }}" type="checkbox" name="accept_terms" value="1" x-model="agreed" required @checked(old('accept_terms'))
            class="mt-0.5 h-4 w-4 shrink-0 rounded border-[#5B6A8E]/40 text-[#060D26] focus:ring-[#FF8A66] focus:ring-offset-0">
        <span class="text-[12.5px] leading-relaxed text-[#060D26]">
            @if($route === 'online')
                @if($moveIn)
                    I understand AbangananHub holds this payment until I confirm move-in (or an admin reviews a problem), then releases it to {{ $landlordName }}.
                @else
                    I understand this rent goes to {{ $landlordName }} right away and is not held by AbangananHub.
                @endif
                @if($fee > 0)
                    The online payment fee (&#8369;{{ number_format($fee, 2) }}) is part of the total and is not refunded unless an admin decides otherwise.
                @endif
            @elseif($route === 'qr')
                I confirm I paid this amount to a QR that {{ $landlordName }} shared in this chat, and the reference number is from my own receipt. I understand AbangananHub doesn&rsquo;t hold, check or refund this money, and a false report can get my account suspended.
            @else
                I confirm I handed this cash to {{ $landlordName }} myself. I understand AbangananHub doesn&rsquo;t hold, check or refund it, and a false report can get my account suspended.
            @endif
            <a href="{{ route('terms') }}" target="_blank" rel="noopener" class="font-bold underline">Read the Terms</a>.
        </span>
    </label>
    @error('accept_terms')
        <p class="mt-1.5 text-[12px] font-semibold text-[#DC2626]">{{ $message }}</p>
    @enderror
</div>
