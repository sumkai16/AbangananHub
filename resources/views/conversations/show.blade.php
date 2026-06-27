@extends('layouts.app', ['searchBar' => false])

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="{{ route('conversations.index') }}" class="inline-flex items-center text-sm font-semibold text-blue-600 hover:text-blue-700 transition">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
            Back to Messages
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        <div class="lg:col-span-2 bg-white border border-gray-100 rounded-2xl shadow-sm flex flex-col overflow-hidden h-[600px]">
            
            <div class="p-4 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-sm shadow-sm">
                        {{ strtoupper(substr($otherParty->first_name, 0, 1)) }}
                    </div>
                    <div>
                        <h1 class="text-base font-bold text-gray-900 leading-tight">
                            {{ $otherParty->first_name }} {{ $otherParty->last_name }}
                        </h1>
                        <p class="text-xs font-medium text-gray-400 mt-0.5">
                            Active Conversation
                        </p>
                    </div>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-100">
                    <span class="w-1.5 h-1.5 mr-1.5 bg-green-500 rounded-full animate-pulse"></span>
                    Online
                </span>
            </div>

            <div id="message-list" class="flex-1 p-6 overflow-y-auto bg-gray-50/30 flex flex-col gap-4 scroll-smooth">
                @foreach ($conversation->messages as $message)
                    @php $isSelf = $message->sender_id === auth()->id(); @endphp
                    <div class="max-w-[75%] {{ $isSelf ? 'self-end bg-blue-600 text-white rounded-2xl rounded-tr-none' : 'self-start bg-white text-gray-900 border border-gray-100 rounded-2xl rounded-tl-none' }} px-4 py-2.5 shadow-sm transition-all">
                        @if(!$isSelf)
                            <p class="text-[11px] font-bold text-blue-600 mb-1 tracking-wide uppercase">
                                {{ $message->sender->first_name }}
                            </p>
                        @endif
                        <p class="text-sm leading-relaxed whitespace-pre-wrap">{{ $message->message }}</p>
                        <div class="flex items-center justify-end mt-1">
                            <p class="text-[10px] tracking-wide {{ $isSelf ? 'text-blue-200' : 'text-gray-400 font-medium' }} message-time" data-sent-at="{{ $message->sent_at->toIso8601String() }}"></p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="p-4 bg-white border-t border-gray-100">
                <form id="message-form" class="flex items-center gap-3 relative">
                    <input type="text" id="message-input" name="message" required maxlength="2000" autofocus
                        class="flex-1 bg-gray-50 border border-gray-200 focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100 rounded-xl px-4 py-3 text-sm text-gray-900 transition outline-none placeholder-gray-400" 
                        placeholder="Write a warm message to {{ $otherParty->first_name }}...">
                    
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 active:scale-95 text-white font-semibold text-sm px-5 py-3 rounded-xl shadow-sm transition inline-flex items-center justify-center space-x-1.5">
                        <span>Send</span>
                        <svg class="w-4 h-4 transform rotate-45" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-5 lg:col-span-1 space-y-4">
            <h3 class="text-sm font-bold text-gray-400 tracking-wider uppercase">Contextual Listing</h3>
            
            <div class="rounded-xl overflow-hidden bg-gray-50 border border-gray-100 p-3 flex flex-col space-y-3">
                <div class="w-full h-32 bg-blue-50 rounded-lg flex items-center justify-center text-blue-500">
                    <svg class="w-10 h-10 opacity-70" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-10.5l8.5-6.75 8.5 6.75M4.5 9v12m15-12v12M9 21v-6a2.25 2.25 0 012.25-2.25h1.5A2.25 2.25 0 0115 15v6M9 9.75h.008v.008H9V9.75zm.375 0h.008v.008H9.375V9.75zm-.375 2.25h.008v.008H9v-.008zm.375 0h.008v.008H9.375v-.008zm-.375 2.25h.008v.008H9v-.008zm.375 0h.008v.008H9.375v-.008zm2.25-4.5h.008v.008h-.008V9.75zm.375 0h.008v.008H12.375V9.75zm-.375 2.25h.008v.008h-.008v-.008zm.375 0h.008v.008H12.375v-.008zm-.375 2.25h.008v.008h-.008v-.008zm.375 0h.008v.008H12.375v-.008zm2.25-4.5h.008v.008h-.008V9.75zm.375 0h.008v.008H15.375V9.75zm-.375 2.25h.008v.008h-.008v-.008zm.375 0h.008v.008H15.375v-.008zm-.375 2.25h.008v.008h-.008v-.008zm.375 0h.008v.008H15.375v-.008z" />
                    </svg>
                </div>
                <div>
                    <h4 class="font-bold text-gray-900 text-sm leading-snug hover:text-blue-600 transition">
                        <a href="{{ route('properties.show', $conversation->property) }}" target="_blank">
                            {{ $conversation->property->title }}
                        </a>
                    </h4>
                    <p class="text-xs text-gray-500 mt-1 flex items-center">
                        <svg class="w-3.5 h-3.5 text-gray-400 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                        </svg>
                        Cebu City, Philippines
                    </p>
                </div>
            </div>

            <div class="border-t border-gray-100 pt-4 space-y-2.5">
                <a href="{{ route('properties.show', $conversation->property) }}" 
                   class="w-full text-center bg-white border border-blue-600 text-blue-600 font-bold text-xs py-3 rounded-xl block hover:bg-blue-50 transition tracking-wide">
                    View Complete Property Details
                </a>
            </div>
        </div>

    </div>
</div>

<script type="module">
    // 1. Format existing message times on page load
    document.querySelectorAll('.message-time').forEach((el) => {
        el.textContent = new Date(el.dataset.sentAt).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    });

    const conversationId = {{ $conversation->conversation_id }};
    const currentUserId = {{ auth()->id() }};
    const messageList = document.getElementById('message-list');
    const messageForm = document.getElementById('message-form');
    const messageInput = document.getElementById('message-input');

    // Auto-scroll to bottom immediately on load
    messageList.scrollTop = messageList.scrollHeight;

    function appendMessage({ sender_id, sender_name, message, sent_at }) {
        const isSelf = sender_id === currentUserId;
        const bubble = document.createElement('div');
        
        // Applying the matching Tailwind redesign variables dynamically to appends
        bubble.className = `max-w-[75%] ${isSelf ? 'self-end bg-blue-600 text-white rounded-2xl rounded-tr-none' : 'self-start bg-white text-gray-900 border border-gray-100 rounded-2xl rounded-tl-none'} px-4 py-2.5 shadow-sm transition-all duration-200`;
        
        bubble.innerHTML = `
            ${!isSelf ? `<p class="text-[11px] font-bold text-blue-600 mb-1 tracking-wide uppercase">${sender_name.split(' ')[0]}</p>` : ''}
            <p class="text-sm leading-relaxed whitespace-pre-wrap">${message}</p>
            <div class="flex items-center justify-end mt-1">
                <p class="text-[10px] tracking-wide ${isSelf ? 'text-blue-200' : 'text-gray-400 font-medium'}">${new Date(sent_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}</p>
            </div>
        `;
        messageList.appendChild(bubble);
        messageList.scrollTop = messageList.scrollHeight;
    }

    messageForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const text = messageInput.value.trim();
        if (!text) return;

        const response = await fetch(`/conversations/${conversationId}/messages`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Socket-ID': window.Echo.socketId(),
            },
            body: JSON.stringify({ message: text }),
        });

        if (!response.ok) {
            console.error('Failed to send message');
            return;
        }

        const data = await response.json();
        appendMessage(data); 
        messageInput.value = '';
    });

    window.Echo.private(`conversation.${conversationId}`)
        .listen('MessageSent', (e) => {
            appendMessage({
                sender_id: e.message.sender_id,
                sender_name: `${e.message.sender.first_name} ${e.message.sender.last_name}`,
                message: e.message.message,
                sent_at: e.message.sent_at || e.message.created_at
            });
        });
</script>
@endsection