@php($searchBar = false)
@extends('layouts.app')

@section('content')
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div class="flex items-end justify-between mb-6 pb-3 border-b border-gray-100">
            <div>
                <h1 class="text-2xl font-extrabold text-[#1A1A2E] tracking-tight">Notifications</h1>
                <p class="text-sm text-gray-500 mt-1 font-medium">Catch up on your latest alerts and activities.</p>
            </div>
            @if($notifications->isNotEmpty())
                <form action="{{ route('notifications.readAll') }}" method="POST">
                    @csrf
                    <button type="submit" class="group flex items-center gap-1.5 text-sm font-semibold text-[#286CD2] hover:text-[#1D4ED8] transition-colors py-1.5 px-3 rounded-full hover:bg-blue-50">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>

        @if($notifications->isEmpty())
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 mt-4 text-center">
                <x-empty-state title="You're all caught up!"
                    message="When you get messages, reservation updates, or other alerts, they'll show up here." />
            </div>
        @else
            <div class="bg-white shadow-sm rounded-2xl border border-gray-100 flex flex-col divide-y divide-gray-100">
                @foreach($notifications as $n)
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
                    <div class="p-4 flex gap-3.5 items-start transition-all duration-200 hover:bg-gray-50 group {{ $n->is_read ? 'opacity-85' : 'relative' }}">
                        @if(!$n->is_read)
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#286CD2]"></div>
                        @endif
                        
                        <div class="mt-0.5 flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center overflow-hidden {{ $n->is_read ? 'bg-gray-100 text-gray-400' : 'bg-[#286CD2] text-white' }} shadow-sm">
                            @if($senderImage)
                                <img src="{{ asset('storage/' . $senderImage) }}" alt="{{ $senderName }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-sm font-bold">{{ $initial }}</span>
                            @endif
                        </div>
                        
                        <div class="flex-1 min-w-0 pr-2">
                            <div class="flex justify-between items-baseline gap-2 mb-0.5">
                                <p class="text-[14.5px] font-semibold text-[#1A1A2E] {{ $n->is_read ? 'font-medium' : '' }} group-hover:text-[#286CD2] transition-colors">{{ $n->title }}</p>
                                <span class="text-[11px] font-medium whitespace-nowrap {{ $n->is_read ? 'text-gray-400' : 'text-[#286CD2]' }}">{{ $n->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-[13px] leading-relaxed {{ $n->is_read ? 'text-gray-500' : 'text-gray-700' }}">{{ $n->message }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex justify-center">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
@endsection