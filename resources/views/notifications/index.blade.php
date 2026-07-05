@php($searchBar = false)
@extends('layouts.app')

@section('content')
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
<<<<<<< HEAD
        <div class="flex items-end justify-between mb-6 pb-3 border-b border-[#F7FCFC]">
            <div>
                <h1 class="text-2xl font-extrabold text-[#156F8C] tracking-tight">Notifications</h1>
                <p class="text-sm text-[#9B9F98] mt-1 font-medium">Catch up on your latest alerts and activities.</p>
=======
        <div class="flex items-end justify-between mb-6 pb-3 border-b border-[#EEF8F8]">
            <div>
                <h1 class="text-2xl font-extrabold text-[#1F2937] tracking-tight">Notifications</h1>
                <p class="text-sm text-[#64748B] mt-1 font-medium">Catch up on your latest alerts and activities.</p>
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
            </div>
            @if($notifications->isNotEmpty())
                <form action="{{ route('notifications.readAll') }}" method="POST">
                    @csrf
<<<<<<< HEAD
                    <button type="submit" class="group flex items-center gap-1.5 text-sm font-semibold text-[#FF8A65] hover:brightness-90 transition-all py-1.5 px-3 rounded-full hover:bg-[#F7FCFC]">
=======
                    <button type="submit" class="group flex items-center gap-1.5 text-sm font-semibold text-[#156F8C] hover:brightness-90 transition-all py-1.5 px-3 rounded-full hover:bg-[#EEF8F8]">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>

        @if($notifications->isEmpty())
<<<<<<< HEAD
            <div class="bg-white rounded-2xl shadow-sm border border-[#F7FCFC] p-10 mt-4 text-center">
=======
            <div class="bg-white rounded-2xl shadow-sm border border-[#EEF8F8] p-10 mt-4 text-center">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                <x-empty-state title="You're all caught up!"
                    message="When you get messages, reservation updates, or other alerts, they'll show up here." />
            </div>
        @else
<<<<<<< HEAD
            <div class="bg-white shadow-sm rounded-2xl border border-[#F7FCFC] flex flex-col divide-y divide-[#F7FCFC]">
=======
            <div class="bg-white shadow-sm rounded-2xl border border-[#EEF8F8] flex flex-col divide-y divide-[#EEF8F8]">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
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
<<<<<<< HEAD
                    <div class="p-4 flex gap-3.5 items-start transition-all duration-200 hover:bg-[#F7FCFC] group {{ $n->is_read ? 'opacity-85' : 'relative bg-[#F7FCFC]/25' }}">
                        @if(!$n->is_read)
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#FF8A65]"></div>
                        @endif

                        <div class="mt-0.5 flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center overflow-hidden {{ $n->is_read ? 'bg-[#F7FCFC] text-[#9B9F98]' : 'bg-[#FF8A65] text-white' }} shadow-sm">
=======
                    <div class="p-4 flex gap-3.5 items-start transition-all duration-200 hover:bg-[#E2E8F0] group {{ $n->is_read ? 'opacity-85' : 'relative bg-[#EEF8F8]/25' }}">
                        @if(!$n->is_read)
                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-[#2AA7A1]"></div>
                        @endif

                        <div class="mt-0.5 flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center overflow-hidden {{ $n->is_read ? 'bg-[#E2E8F0] text-[#64748B]' : 'bg-[#2AA7A1] text-white' }} shadow-sm">
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
                            @if($senderImage)
                                <img src="{{ asset('storage/' . $senderImage) }}" alt="{{ $senderName }}" class="w-full h-full object-cover">
                            @else
                                <span class="text-sm font-bold">{{ $initial }}</span>
                            @endif
                        </div>

                        <div class="flex-1 min-w-0 pr-2">
                            <div class="flex justify-between items-baseline gap-2 mb-0.5">
<<<<<<< HEAD
                                <p class="text-[14.5px] font-semibold text-[#156F8C] {{ $n->is_read ? 'font-medium' : '' }} group-hover:text-[#FF8A65] transition-colors">{{ $n->title }}</p>
                                <span class="text-[11px] font-medium whitespace-nowrap {{ $n->is_read ? 'text-[#9B9F98]' : 'text-[#FF8A65]' }}">{{ $n->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-[13px] leading-relaxed {{ $n->is_read ? 'text-[#9B9F98]' : 'text-[#156F8C]/80' }}">{{ $n->message }}</p>
=======
                                <p class="text-[14.5px] font-semibold text-[#1F2937] {{ $n->is_read ? 'font-medium' : '' }} group-hover:text-[#156F8C] transition-colors">{{ $n->title }}</p>
                                <span class="text-[11px] font-medium whitespace-nowrap {{ $n->is_read ? 'text-[#64748B]' : 'text-[#156F8C]' }}">{{ $n->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="text-[13px] leading-relaxed {{ $n->is_read ? 'text-[#64748B]' : 'text-[#1F2937]/80' }}">{{ $n->message }}</p>
>>>>>>> 69fc64747deeb55b121790f6e9a686054594ede1
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
