@php($searchBar = false)
@extends('layouts.app')

@section('content')
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-[#2A2523]">Notifications</h1>
                <p class="text-sm text-[#9B9F98] mt-1">Your recent alerts and updates.</p>
            </div>
            @if($notifications->isNotEmpty())
                <form action="{{ route('notifications.readAll') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-[#2A2523] hover:underline">
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>

        @if($notifications->isEmpty())
            <x-empty-state title="No notifications yet"
                message="You'll see messages, reservation updates, and other alerts here." />
        @else
            <div class="divide-y divide-gray-100 rounded-2xl border border-gray-100 overflow-hidden">
                @foreach($notifications as $n)
                    <div class="px-5 py-4 flex gap-3 items-start {{ $n->is_read ? '' : 'bg-[#D7E8F3]/20' }}">
                        <span
                            class="mt-1.5 flex-shrink-0 w-2 h-2 rounded-full {{ $n->is_read ? 'bg-transparent' : 'bg-[#61B2F0]' }}"></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-[#2A2523]">{{ $n->title }}</p>
                            <p class="text-sm text-[#9B9F98] mt-0.5">{{ $n->message }}</p>
                            <p class="text-xs text-[#9B9F98] mt-1">{{ $n->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
@endsection