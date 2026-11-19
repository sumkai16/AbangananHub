@if($notifications->isEmpty())
    <div class="px-4 py-8 text-center">
        <svg class="w-8 h-8 mx-auto text-[#5B6A8E] mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"
            stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        <p class="text-[13px] font-medium text-[#060D26]">No notifications yet</p>
        <p class="text-[12px] text-[#5B6A8E] mt-0.5">When you get updates, they'll appear here.</p>
    </div>
@else
    <div class="flex items-center justify-between px-4 pt-3 pb-2">
        <span class="text-[13px] font-bold text-[#060D26]">Notifications</span>
        @if($unreadCount > 0)
            <button type="button" onclick="markAllNotificationsRead()"
                class="text-[12px] font-semibold text-[#060D26] hover:brightness-95 transition-all">
                Mark all read
            </button>
        @endif
    </div>

    <div class="max-h-[360px] overflow-y-auto divide-y divide-[#E2E4EC]">
        @foreach($notifications as $n)
            @include('notifications.partials._row', ['n' => $n])
        @endforeach
    </div>

    <a href="{{ route('notifications.index') }}"
        class="block text-center text-[12px] font-semibold text-[#060D26] py-2.5 border-t border-[#E2E4EC] hover:bg-[#E2E4EC]/50 transition-colors">
        View all notifications
    </a>
@endif