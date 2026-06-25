@forelse($notifications as $n)
    <button type="button" data-id="{{ $n->notification_id }}" data-read="{{ $n->is_read ? '1' : '0' }}"
        @if($n->conversation_id) {{-- TODO: confirm this is your real conversation show route name --}}
        data-href="{{ route('conversations.show', $n->conversation_id) }}" @endif
        class="notif-item w-full text-left px-4 py-3 flex gap-3 items-start hover:bg-[#D7E8F3]/40 transition-colors {{ $n->is_read ? '' : 'bg-[#D7E8F3]/20' }}">
        <span
            class="unread-dot mt-1.5 flex-shrink-0 w-2 h-2 rounded-full {{ $n->is_read ? 'bg-transparent' : 'bg-[#61B2F0]' }}"></span>
        <span class="flex-1 min-w-0">
            <span class="block text-sm font-semibold text-[#2A2523] truncate">{{ $n->title }}</span>
            <span class="block text-sm text-[#9B9F98] line-clamp-2">{{ $n->message }}</span>
            <span class="block text-xs text-[#9B9F98] mt-1">{{ $n->created_at->diffForHumans() }}</span>
        </span>
    </button>
@empty
    <div class="px-4 py-10 text-center text-sm text-[#9B9F98]">No notifications yet.</div>
@endforelse