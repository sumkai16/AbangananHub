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
                // Icon + tint per notification type. The old version assumed
                // every notification was a message and rendered a sender
                // avatar, falling back to scraping a name out of the title
                // with a regex — which produced a random initial for every
                // non-message type.
                $types = [
                    'reservation'  => ['#156F8C', 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'],
                    'agreement'    => ['#2AA7A1', 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'],
                    'payment'      => ['#22C55E', 'M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                    'verification' => ['#156F8C', 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                    'listing'      => ['#2AA7A1', 'M2.25 21h19.5m-18-10.5l8.5-6.75 8.5 6.75M4.5 9v12m15-12v12M9 21v-6a2.25 2.25 0 012.25-2.25h1.5A2.25 2.25 0 0115 15v6'],
                    'review'       => ['#FBBF24', 'M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z'],
                    'report'       => ['#EF4444', 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z'],
                    'account'      => ['#64748B', 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z'],
                ];
                [$tint, $iconPath] = $types[$n->type] ?? ['#64748B', 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'];

                $target = $n->link
                    ?: ($n->conversation_id
                        ? route('conversations.index', ['active' => $n->conversation_id])
                        : route('notifications.index'));
            @endphp

            <div class="px-4 py-3 flex gap-3 items-start hover:bg-[#E2E8F0]/50 transition-colors cursor-pointer {{ !$n->is_read ? 'bg-[#EEF8F8]/30' : '' }}"
                onclick="handleNotificationClick({{ $n->notification_id }}, '{{ $target }}')">

                <div class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center"
                    style="background-color: {{ $tint }}1A">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="{{ $tint }}" stroke-width="1.9">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconPath }}" />
                    </svg>
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