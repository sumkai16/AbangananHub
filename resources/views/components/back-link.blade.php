{{--
    The one page-level "go back" link: left chevron + "Back" (DESIGN.md: one back control everywhere).
    `to` is the destination, read aloud for screen readers only ("Back to reports").
    `tone="light"` is for use on a dark photo or navy ground (the auth panel).

    Where it goes: Back returns to the page the person actually came from (browser history), so a listing opened
    from a chat goes back to that chat, not to the listings page. `href` is only the fallback, used when there is
    no earlier page of this site (a pasted link, a new tab, a refresh from outside). `fixed` turns the history
    behaviour off, for form and wizard pages where the previous page is a form step or a login screen and Back
    must always land on `href`.

    Wizard "Previous" buttons and pagination are a different action and do not use this.
--}}
@props(['href', 'to' => null, 'tone' => 'default', 'fixed' => false])

<a href="{{ $href }}"
    @unless($fixed)
        {{-- An earlier page of this site is behind us when: this entry was made by page-swap (state.swapped; the
             referrer is stale then), or the referrer is another page of this site that is not the login screen.
             Never inside an iframe. Everything goes through `window.`: an inline handler's scope includes
             `document`, so a bare `URL` would be document.URL (a string) and `new URL()` would throw. --}}
        onclick="var w=window,h=w.history,s=h.state&&h.state.swapped,ok=false;if(w.self!==w.top||h.length<2)return;try{var r=new w.URL(document.referrer);ok=r.origin===w.location.origin&&r.pathname!==w.location.pathname&&!/^\/(login|register)\/?$/.test(r.pathname);}catch(e){}if(s||ok){h.back();return false;}"
    @endunless
    {{ $attributes->class([
        'inline-flex items-center gap-1.5 w-fit text-[13px] font-semibold transition-colors duration-200 cursor-pointer',
        'text-[#5B6A8E] hover:text-[#060D26]' => $tone === 'default',
        'text-white/90 hover:text-white' => $tone === 'light',
    ]) }}>
    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
    </svg>
    <span>Back</span>@if($to)<span class="sr-only"> to {{ $to }}</span>@endif
</a>
