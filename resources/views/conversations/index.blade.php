@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-3xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Messages</h1>

        @forelse ($conversations as $conversation)
            @php
                $otherParty = auth()->id() === $conversation->tenant_id
                    ? $conversation->landlord
                    : $conversation->tenant;
            @endphp
            <a href="{{ route('conversations.show', $conversation) }}" class="block border-b border-gray-200 py-4">
                <div class="flex justify-between">
                    <span class="font-semibold">{{ $otherParty->first_name }} {{ $otherParty->last_name }}</span>
                    <span class="text-sm text-gray-500">{{ $conversation->property->title }}</span>
                </div>
                <p class="text-gray-600 text-sm mt-1">
                    {{ $conversation->latestMessage->message ?? 'No messages yet.' }}
                </p>
            </a>
        @empty
            <p class="text-gray-500">No conversations yet.</p>
        @endforelse
    </div>
@endsection