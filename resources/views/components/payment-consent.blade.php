@props(['role'])
{{-- The one sentence a party agrees to about how money moves, worded for the route in use. --}}
@if(\App\Support\Payments::online())
    @if($role === 'tenant')
        I understand that if I pay online, AbangananHub holds my payment until I confirm move-in and only then releases it to the landlord. If I pay my landlord directly, AbangananHub only records the payment and doesn't hold, check or refund it, so I will only pay a QR my landlord shared in our chat.
    @else
        I agree that an online payment from the tenant is held by AbangananHub until the tenant confirms move-in. If the tenant pays me directly, I will confirm it only after I see it in my own account, and AbangananHub doesn't hold or guarantee that money.
    @endif
@else
    @if($role === 'tenant')
        I understand that I pay my landlord directly. AbangananHub records the payment but doesn't hold, check or refund the money, so I will only pay a QR my landlord shared in our chat.
    @else
        I understand that my tenant pays me directly. I will confirm a payment only after I see it in my own account, and AbangananHub doesn't hold or guarantee the money.
    @endif
@endif
