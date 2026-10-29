@props(['role'])
{{-- The one sentence a party agrees to about how money moves, worded for the route in use. --}}
@if(\App\Support\Payments::online())
    @if($role === 'tenant')
        I understand that my payment will be held by AbangananHub until I confirm move-in. Funds will only be released to the landlord after I verify that the unit matches the listing.
    @else
        I agree that the tenant's payment will be held by AbangananHub until the tenant confirms move-in. Funds will be released only after tenant verification.
    @endif
@else
    @if($role === 'tenant')
        I understand that I pay my landlord directly. AbangananHub records the payment but doesn't hold, check or refund the money, so I will only pay a QR my landlord shared in our chat.
    @else
        I understand that my tenant pays me directly. I will confirm a payment only after I see it in my own account, and AbangananHub doesn't hold or guarantee the money.
    @endif
@endif
