@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-8">
        <div class="mb-4">
            @php
                $otherParty = auth()->id() === $conversation->tenant_id
                    ? $conversation->landlord
                    : $conversation->tenant;
            @endphp
            <h1 class="text-xl font-bold">{{ $otherParty->first_name }} {{ $otherParty->last_name }}</h1>
            <p class="text-sm text-gray-500">{{ $conversation->property->title }}</p>
        </div>

        <div id="message-list" class="border border-gray-200 rounded-md p-4 h-96 overflow-y-auto flex flex-col gap-3">
            @foreach ($conversation->messages as $message)
                <div
                    class="max-w-xs {{ $message->sender_id === auth()->id() ? 'self-end bg-blue-100' : 'self-start bg-gray-100' }} rounded-md px-3 py-2">
                    <p class="text-xs text-gray-500">{{ $message->sender->first_name }}</p>
                    <p>{{ $message->message }}</p>
                    <p class="text-xs text-gray-400 message-time" data-sent-at="{{ $message->sent_at->toIso8601String() }}"></p>
                </div>
            @endforeach
        </div>

        <form id="message-form" class="mt-4 flex gap-2">
            <input type="text" id="message-input" name="message" required maxlength="2000"
                class="flex-1 border border-gray-300 rounded-md px-3 py-2" placeholder="Type a message...">
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md">Send</button>
        </form>
    </div>

    <script type="module">
        document.querySelectorAll('.message-time').forEach((el) => {
            el.textContent = new Date(el.dataset.sentAt).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        });

        const conversationId = {{ $conversation->conversation_id }};
        const currentUserId = {{ auth()->id() }};
        const messageList = document.getElementById('message-list');
        const messageForm = document.getElementById('message-form');
        const messageInput = document.getElementById('message-input');

        function appendMessage({ sender_id, sender_name, message, sent_at }) {
            const isSelf = sender_id === currentUserId;
            const bubble = document.createElement('div');
            bubble.className = `max-w-xs ${isSelf ? 'self-end bg-blue-100' : 'self-start bg-gray-100'} rounded-md px-3 py-2`;
            bubble.innerHTML = `
                <p class="text-xs text-gray-500">${sender_name}</p>
                <p>${message}</p>
                <p class="text-xs text-gray-400">${new Date(sent_at).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' })}</p>
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
                appendMessage(e);
            });
    </script>
@endsection