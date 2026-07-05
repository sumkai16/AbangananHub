@php $currentUserId = auth()->id(); @endphp

<div class="flex items-center justify-between px-4 py-3 border-b border-[#E2E8F0]">
    <span class="text-[14px] font-bold text-[#1F2937]">Messages</span>
    <div class="flex items-center gap-2">
        @if($unreadCount > 0)
            <span class="text-[11px] font-bold text-[#156F8C] bg-[#EEF8F8] px-2 py-0.5 rounded-md">{{ $unreadCount }}
                unread</span>
        @endif
        <button @click="closeBubblePanel()"
            class="w-6 h-6 flex items-center justify-center rounded-full hover:bg-[#E2E8F0] text-[#64748B] transition-colors">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</div>

<div class="overflow-y-auto" style="max-height: 340px;">
    @forelse($conversations as $conversation)
        @php
            $otherParty = $currentUserId === $conversation->tenant_id
                ? $conversation->landlord
                : $conversation->tenant;
            $lastMsg = $conversation->latestMessage;
            $hasUnread = $lastMsg
                && $lastMsg->sender_id !== $currentUserId
                && !$lastMsg->is_read;
            $isSender = $lastMsg && $lastMsg->sender_id === $currentUserId;
        @endphp

        <a href="{{ route('conversations.index', ['active' => $conversation->conversation_id]) }}"
            class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-[#E2E8F0]/50 border-b border-[#E2E8F0] last:border-b-0
                    {{ $hasUnread ? 'bg-[#EEF8F8]/30 border-l-[3px] border-l-[#2AA7A1]' : 'border-l-[3px] border-l-transparent' }}">

            <div class="relative shrink-0">
                <div class="w-10 h-10 rounded-full bg-[#1F2937] flex items-center justify-center">
                    <span
                        class="text-white text-[14px] font-bold">{{ strtoupper(substr($otherParty->first_name, 0, 1)) }}</span>
                </div>
                @if($hasUnread)
                    <div class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-[#2AA7A1] border-2 border-white"></div>
                @endif
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[13px] font-bold text-[#1F2937] truncate">{{ $otherParty->first_name }}
                        {{ $otherParty->last_name }}</span>
                    <span
                        class="text-[10px] text-[#64748B] shrink-0">{{ $lastMsg ? $lastMsg->sent_at->diffForHumans(null, true) : '' }}</span>
                </div>
                <div class="text-[11px] text-[#64748B] truncate mt-0.5">
                    {{ $conversation->property->title }}@if($conversation->unit) ·
                    {{ $conversation->unit->unit_label }}@endif
                </div>
                @if($lastMsg)
                    <div class="text-[12px] truncate mt-0.5 {{ $hasUnread ? 'font-bold text-[#1F2937]' : 'text-[#64748B]' }}">
                        {{ $isSender ? 'You: ' : '' }}{{ Str::limit($lastMsg->message, 40) }}
                    </div>
                @endif
            </div>
        </a>
    @empty
        <div class="px-4 py-10 text-center">
            <svg class="w-8 h-8 mx-auto text-[#64748B] mb-2" fill="none" stroke="currentColor" stroke-width="1.5"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
            <p class="text-[13px] font-bold text-[#1F2937]">No conversations yet</p>
            <p class="text-[11px] text-[#64748B] mt-1">Your messages will appear here.</p>
        </div>
    @endforelse
</div>

<a href="{{ route('conversations.index') }}"
    class="flex items-center justify-center gap-1.5 px-4 py-3 border-t border-[#E2E8F0] text-[13px] font-bold text-[#156F8C] hover:bg-[#EEF8F8]/30 transition-colors">
    View all messages
    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
    </svg>
</a>