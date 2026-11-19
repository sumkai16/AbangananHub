{{--
    A person's name as a link to their profile, in a new tab, underlined on hover. Where the viewer has no page to
    see (see App\Support\ProfileLinks) it prints the plain name, so it is safe to use everywhere a name appears.

    <x-person-link :user="$reservation->tenant" />                       full name
    <x-person-link :user="$landlord" :name="$landlord->first_name" />    own wording
    <x-person-link :user="$u" :name="$u->first_name" class="rounded-full"><img ...></x-person-link>   an avatar: the slot
        is what is shown, and the name becomes the link's label for screen readers.
    Inside a clickable row or card the click is kept from reaching it. Phones show no underline until tapped (no hover).
--}}
@props(['user' => null, 'name' => null])
@php
    $label = $name ?? ($user ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) : '');
    $label = $label !== '' ? $label : ($user->name ?? '');
    $url = \App\Support\ProfileLinks::url($user);
    $wraps = $slot->isNotEmpty();
@endphp
@if($url)
    <a href="{{ $url }}" target="_blank" rel="noopener" @click.stop
        @if($wraps) aria-label="{{ $label }} (opens profile in a new tab)" @endif
        {{ $attributes->class($wraps ? ['cursor-pointer transition-opacity duration-200 hover:opacity-80'] : ['cursor-pointer underline-offset-2 decoration-1 hover:underline focus-visible:underline']) }}>@if($wraps){{ $slot }}@else{{ $label }}<span class="sr-only"> (opens profile in a new tab)</span>@endif</a>
@elseif($wraps)
    <span {{ $attributes }}>{{ $slot }}</span>
@else
    <span {{ $attributes }}>{{ $label }}</span>
@endif
