{{--
    Conversations are private to admins until a participant asks (plans/conversation-scam-detection.md, option B).
    Shared by chat-panel (inquiry threads) and tenancy-panel (tenancy threads). Needs $conversation, $isTenant, $isLandlord.
      part = 'banner':  shown to both people while an administrator is allowed to read the thread
      part = 'request': the way to ask an administrator to read it
--}}
@if($isTenant || $isLandlord)
    @if($part === 'banner' && $conversation->adminMayRead())
        <div class="flex-shrink-0 border-b border-[#FF8A66]/25 bg-[#FFF6F2] px-5 py-2 text-[11.5px] leading-snug text-[#060D26]" role="status">
            <span class="font-semibold">An AbangananHub administrator can read this conversation</span>
            because a review was requested {{ $conversation->admin_review_requested_at?->diffForHumans() }}. It is private again when they finish.
        </div>
    @elseif($part === 'request' && ! $conversation->adminMayRead())
        <form method="POST" action="{{ route('conversations.adminReview.request', $conversation) }}"
            onsubmit="return confirm('Ask an AbangananHub administrator to read this conversation? Only you and the other person can read it until you do.')"
            class="{{ $formClass ?? '' }} flex flex-wrap items-center gap-x-3 gap-y-1 px-1">
            @csrf
            <p class="min-w-0 flex-1 basis-56 text-[11px] leading-snug text-[#5B6A8E]">Conversations are private. Something wrong, like a scam or a dispute?</p>
            <button type="submit" class="min-h-[44px] text-[11px] font-bold text-[#060D26] underline underline-offset-2 hover:text-[#B35A3D] transition-colors cursor-pointer">Ask an admin to review</button>
        </form>
    @endif
@endif
