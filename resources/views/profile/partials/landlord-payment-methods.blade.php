{{-- Settings > Payments (landlords): which ways tenants may pay, for the move-in payment and monthly rent alike
     (App\Support\PaymentMethods). Saved by Landlord\PaymentMethodController. Online is offered only while online
     payments are switched on, and only once GCash is saved below, since that is where AbangananHub pays it out. --}}
@php
    $onlineSwitch = \App\Support\Payments::online();
    $hasGcash = $user->hasPayoutDestination();
    $acceptsOnline = (bool) ($user->payoutAccount?->accepts_online ?? true);
    $methods = array_filter([
        'online' => $onlineSwitch ? [
            'title' => 'Online',
            'body' => 'GCash or QR Ph through AbangananHub. Move-in money is held until the tenant moves in, then paid out to your GCash.',
            'on' => $hasGcash && (bool) old('accepts_online', $acceptsOnline),
            'disabled' => ! $hasGcash,
            'note' => $hasGcash ? null : 'Save your GCash below to turn this on.',
        ] : null,
        'qr' => [
            'title' => 'Your QR',
            'body' => 'You share your own QR in the chat. The tenant pays it and tells you, and you confirm.',
            'on' => (bool) old('accepts_qr', $user->accepts_qr_payments ?? true),
            'disabled' => false,
            'note' => null,
        ],
        'cash' => [
            'title' => 'Cash',
            'body' => 'Handed to you in person. You record it here and the tenant is told.',
            'on' => (bool) old('accepts_cash', $user->accepts_cash_payments ?? true),
            'disabled' => false,
            'note' => null,
        ],
    ]);
@endphp

{{-- Inside Account Settings the x-settings-panel draws the card and the header; elsewhere (admin) this partial does. --}}
@unless($inPanel ?? false)
<div class="rounded-2xl ring-1 ring-[#5B6A8E]/10 shadow-[0_2px_12px_rgba(6,13,38,0.05)] bg-white p-4 sm:p-8">
    <div class="mb-5 sm:mb-6 border-b border-[#E2E4EC] pb-4 sm:pb-6 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-[#ECEEF6] flex items-center justify-center shrink-0">
            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
            </svg>
        </div>
        <div>
            <h2 class="text-[15px] font-normal text-[#060D26]">How tenants can pay you</h2>
            <p class="mt-0.5 text-sm text-[#5B6A8E]">Tenants only see the ways you turn on, when they pay to move in and for monthly rent.</p>
        </div>
    </div>
@endunless

    @if ($inPanel ?? false)
        {{-- Inside the Settings > Payments panel this is the first of two parts (then "Where your payouts go"). --}}
        <div class="mb-4">
            <h3 class="text-[15px] font-semibold text-[#060D26]">How tenants can pay you</h3>
            <p class="mt-0.5 text-[13px] leading-relaxed text-[#5B6A8E]">Tenants only see the ways you turn on, when they pay to move in and for monthly rent.</p>
        </div>
    @endif
    <form method="POST" action="{{ route('landlord.paymentMethods.update') }}">
        @csrf
        @method('PATCH')

        @error('payment_methods')
            <p class="mb-4 rounded-xl bg-[#EF4444]/[0.07] px-3.5 py-2.5 text-[13px] font-medium text-[#DC2626]" role="alert">{{ $message }}</p>
        @enderror

        <fieldset>
            <legend class="sr-only">Ways tenants can pay you</legend>
            <div class="grid grid-cols-1 gap-3 {{ count($methods) === 3 ? 'md:grid-cols-3' : 'md:grid-cols-2' }}">
                @foreach ($methods as $key => $method)
                    {{-- A checkbox per way, drawn as a card. The hidden 0 posts when it is unticked (or disabled). --}}
                    <input type="hidden" name="accepts_{{ $key }}" value="0">
                    <label class="relative block {{ $method['disabled'] ? 'cursor-not-allowed' : 'cursor-pointer' }}">
                        <input type="checkbox" name="accepts_{{ $key }}" value="1" class="peer sr-only"
                            @checked($method['on']) @disabled($method['disabled'])
                            @if ($method['note']) aria-describedby="method-note-{{ $key }}" @endif>
                        <span class="flex h-full flex-col rounded-2xl border border-[#E2E4EC] bg-white p-4 transition-colors
                            peer-checked:border-[#FF8A66] peer-checked:bg-[#FF8A66]/[0.07] peer-checked:ring-2 peer-checked:ring-[#FF8A66]
                            peer-focus-visible:ring-2 peer-focus-visible:ring-[#060D26]/30
                            peer-checked:[&_.mark]:border-[#FF8A66] peer-checked:[&_.mark]:bg-[#FF8A66] peer-checked:[&_.mark-icon]:block
                            peer-disabled:bg-[#F7F8FC] {{ $method['disabled'] ? '' : 'hover:bg-[#F7F8FC]' }}">
                            <span class="flex items-center justify-between gap-3">
                                <span class="text-[14px] font-bold {{ $method['disabled'] ? 'text-[#5B6A8E]' : 'text-[#060D26]' }}">{{ $method['title'] }}</span>
                                {{-- On/off mark: an empty ring when off, a filled check when on. --}}
                                <span class="mark flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 border-[#5B6A8E]/40" aria-hidden="true">
                                    <svg class="mark-icon hidden h-3 w-3 text-[#060D26]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                </span>
                            </span>
                            <span class="mt-1 text-[12.5px] leading-relaxed text-[#5B6A8E]">{{ $method['body'] }}</span>
                            @if ($method['note'])
                                <span id="method-note-{{ $key }}" class="mt-2 text-[12px] font-semibold text-[#B45309]">{{ $method['note'] }}</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>
        @unless ($onlineSwitch)
            <input type="hidden" name="accepts_online" value="0">
        @endunless

        <div class="mt-6 flex flex-col gap-3 border-t border-[#E2E4EC] pt-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-[12px] leading-relaxed text-[#5B6A8E]">Keep at least one on. A tenant halfway through a payment can still finish it.</p>
            <button type="submit"
                class="inline-flex h-11 shrink-0 items-center justify-center rounded-full bg-[#FF8A66] px-6 text-[14px] font-semibold text-[#060D26] transition-colors hover:bg-[#E96F4F] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#060D26]/30 cursor-pointer">
                Save payment methods
            </button>
        </div>
    </form>
@unless($inPanel ?? false)
</div>
@endunless
