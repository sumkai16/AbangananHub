@if($notifications->isEmpty())
    <div class="px-4 py-8 text-center">
        <svg class="w-8 h-8 mx-auto text-[#64748B] mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"
            stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        <p class="text-[13px] font-medium text-[#1F2937]">No notifications yet</p>
        <p class="text-[12px] text-[#64748B] mt-0.5">When you get updates, they'll appear here.</p>
    </div>
@else
    <div class="flex items-center justify-between px-4 pt-3 pb-2">
        <span class="text-[13px] font-bold text-[#1F2937]">Notifications</span>
        @if($unreadCount > 0)
            <button type="button" onclick="markAllNotificationsRead()"
                class="text-[12px] font-semibold text-[#156F8C] hover:brightness-95 transition-all">
                Mark all read
            </button>
        @endif
    </div>

    <div class="max-h-[360px] overflow-y-auto divide-y divide-[#E2E8F0]">
        @foreach($notifications as $n)
            @php
                $senderName = '';
                $senderImage = null;
                if ($n->conversation) {
                    $otherUser = $n->conversation->tenant_id === $n->user_id
                        ? $n->conversation->landlord
                        : $n->conversation->tenant;
                    if ($otherUser) {
                        $senderName = $otherUser->first_name;
                        $senderImage = $otherUser->profile_picture;
                    }
                }
                if (!$senderName) {
                    if (preg_match('/from\s+([A-Za-z]+)/i', $n->title, $matches)) {
                        $senderName = $matches[1];
                    } else {
                        $senderName = $n->title;
                    }
                }
                $initial = strtoupper(substr($senderName, 0, 1));
            @endphp

            <div class="px-4 py-3 flex gap-3 items-start hover:bg-[#E2E8F0]/50 transition-colors cursor-pointer {{ !$n->is_read ? 'bg-[#EEF8F8]/30' : '' }}"
                onclick="handleNotificationClick({{ $n->notification_id }}, '{{ $n->conversation_id ? route('conversations.show', $n->conversation_id) : route('notifications.index') }}')">

                <div
                    class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center overflow-hidden {{ $n->is_read ? 'bg-[#E2E8F0] text-[#64748B]' : 'bg-[#EEF8F8] text-[#1F2937]' }}">
                    @if($senderImage)
                        <img src="{{ $senderImage }}" alt="" class="w-full h-full object-cover">
                    @else
                        <span class="text-[12px] font-bold">{{ $initial }}</span>
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-baseline justify-between gap-2">
                        <p
                            class="text-[13px] {{ $n->is_read ? 'font-medium text-[#1F2937]' : 'font-bold text-[#1F2937]' }} truncate">
                            {{ $n->title }}</p>
                        @if(!$n->is_read)
                            <span class="flex-shrink-0 w-2 h-2 rounded-full bg-[#2AA7A1]"></span>
                        @endif
                    </div>
                    <p class="text-[12px] text-[#64748B] truncate mt-0.5">{{ $n->message }}</p>
                    <p class="text-[11px] text-[#64748B] mt-1">{{ $n->created_at->diffForHumans() }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <a href="{{ route('notifications.index') }}"
        class="block text-center text-[12px] font-semibold text-[#1F2937] py-2.5 border-t border-[#E2E8F0] hover:bg-[#E2E8F0]/50 transition-colors">
        View all notifications
    </a>
@endif