@forelse($notifications as $n)
    <?php
        $senderName = '';
        $senderImage = null;
        if ($n->conversation) {
            $otherUser = $n->conversation->tenant_id === $n->user_id ? $n->conversation->landlord : $n->conversation->tenant;
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
    ?>
    <button type="button" data-id="{{ $n->notification_id }}" data-read="{{ $n->is_read ? '1' : '0' }}"
        @if($n->conversation_id)
        data-href="{{ route('conversations.show', $n->conversation_id) }}" @endif
        class="notif-item group relative w-full text-left px-4 py-3 flex gap-3 items-start bg-white hover:bg-gray-50 transition-all duration-200 border-b border-gray-50 last:border-0 {{ $n->is_read ? 'opacity-85' : '' }}">
        
        @if(!$n->is_read)
            <div class="absolute left-0 top-0 bottom-0 w-[3px] bg-[#2AA7A1]"></div>
        @endif
        
        <div class="mt-0.5 flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center overflow-hidden {{ $n->is_read ? 'bg-gray-100 text-gray-400' : 'bg-[#2AA7A1] text-white' }} shadow-sm">
            @if($senderImage)
                <img src="{{ asset('storage/' . $senderImage) }}" alt="{{ $senderName }}" class="w-full h-full object-cover">
            @else
                <span class="text-xs font-bold">{{ $initial }}</span>
            @endif
        </div>
        
        <span class="flex-1 min-w-0 flex flex-col justify-center">
            <span class="flex justify-between items-baseline gap-1.5 mb-0.5">
                <span class="block text-[13.5px] font-semibold text-[#1A1A2E] truncate group-hover:text-[#156F8C] transition-colors {{ $n->is_read ? 'font-medium' : '' }}">{{ $n->title }}</span>
                <span class="block text-[10px] font-medium whitespace-nowrap {{ $n->is_read ? 'text-gray-400' : 'text-[#156F8C]' }}">{{ $n->created_at->diffForHumans() }}</span>
            </span>
            <span class="block text-[12px] leading-relaxed line-clamp-2 {{ $n->is_read ? 'text-gray-500' : 'text-gray-700' }}">{{ $n->message }}</span>
        </span>
    </button>
@empty
    <div class="px-5 py-10 flex flex-col items-center justify-center gap-3">
        <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center">
            <svg class="w-5 h-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
            </svg>
        </div>
        <p class="text-sm font-medium text-gray-400">No notifications yet</p>
    </div>
@endforelse