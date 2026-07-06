@php
    $searchBar = false;
@endphp
@extends('layouts.app')

@section('content')
<div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8 py-10">

    {{-- Header --}}
    <div class="flex items-end justify-between mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-[#1F2937] tracking-tight">Notifications</h1>
            <p class="text-sm text-[#64748B] mt-1 font-medium">
                {{ $unreadCount }} unread notification{{ $unreadCount !== 1 ? 's' : '' }}
            </p>
        </div>
        @if($notifications->isNotEmpty())
            <form action="{{ route('notifications.readAll') }}" method="POST">
                @csrf
                <button type="submit"
                    class="flex items-center gap-1.5 text-sm font-semibold text-[#156F8C] hover:brightness-90 transition-all py-1.5 px-3 rounded-full hover:bg-[#EEF8F8]">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Mark all as read
                </button>
            </form>
        @endif
    </div>

    {{-- Tabs --}}
    <div class="flex items-center gap-1 mb-5">
        @foreach(['all' => 'All', 'unread' => 'Unread', 'review' => 'Reviews', 'reservation' => 'Reservations'] as $key => $label)
            @php
                $isActive = $tab === $key;
                $params = array_merge(request()->except(['tab', 'page', 'selected']), $key !== 'all' ? ['tab' => $key] : []);
            @endphp
            <a href="{{ route('notifications.index', $params) }}"
                class="px-4 py-2 rounded-full text-[13px] font-semibold transition-colors
                    {{ $isActive ? 'bg-[#156F8C] text-white' : 'bg-white text-[#1F2937] border border-[#E2E8F0] hover:brightness-95' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($notifications->isEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-[#E2E8F0] p-10 text-center">
            <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] border border-[#E2E8F0] flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                </svg>
            </div>
            <p class="text-[15px] font-bold text-[#1F2937]">
                {{ $tab === 'unread' ? 'You\'re all caught up!' : 'No notifications found' }}
            </p>
            <p class="text-[13px] text-[#64748B] mt-1">
                {{ $tab === 'unread' ? 'All notifications have been read.' : 'When you get updates, they\'ll show up here.' }}
            </p>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-0 bg-white border border-[#E2E8F0] rounded-2xl overflow-hidden shadow-sm min-h-[500px]">

            {{-- LEFT: Notification list --}}
            <div class="{{ $selected ? 'hidden' : 'flex' }} lg:flex border-r border-[#E2E8F0] flex-col divide-y divide-[#E2E8F0] overflow-y-auto max-h-[700px]">
                @foreach($notifications as $n)
                    @php
                        $isSelected = $selected && $selected->notification_id === $n->notification_id;
                        $iconConfig = match(true) {
                            str_contains($n->title, 'Review') || str_contains($n->title, 'Reply') => ['bg' => 'bg-[#FBBF24]', 'icon' => 'M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.563.563 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z'],
                            str_contains($n->title, 'Approved') || str_contains($n->title, 'approved') => ['bg' => 'bg-[#22C55E]', 'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                            str_contains($n->title, 'Rejected') || str_contains($n->title, 'rejected') || str_contains($n->title, 'Cancel') => ['bg' => 'bg-[#EF4444]', 'icon' => 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                            str_contains($n->title, 'Reservation') || str_contains($n->title, 'reservation') => ['bg' => 'bg-[#2AA7A1]', 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'],
                            default => ['bg' => 'bg-[#156F8C]', 'icon' => 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0'],
                        };
                    @endphp
                    <a href="{{ route('notifications.index', array_merge(request()->except(['selected', 'page']), ['selected' => $n->notification_id])) }}"
                        class="block p-4 transition-all duration-200 hover:bg-[#EEF8F8] {{ $isSelected ? 'bg-[#EEF8F8] border-l-[3px] border-l-[#2AA7A1]' : 'border-l-[3px] border-l-transparent' }} {{ $n->is_read && !$isSelected ? 'opacity-70' : '' }}">
                        <div class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 {{ $iconConfig['bg'] }}">
                                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $iconConfig['icon'] }}" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="text-[13px] font-semibold text-[#1F2937] truncate">{{ $n->title }}</p>
                                    @if(!$n->is_read)
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#2AA7A1] flex-shrink-0"></span>
                                    @endif
                                </div>
                                <p class="text-[12px] text-[#64748B] mt-0.5 line-clamp-1">{{ $n->message }}</p>
                                <p class="text-[11px] text-[#64748B]/70 mt-1">{{ $n->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- RIGHT: Detail panel --}}
            <div class="{{ $selected ? 'flex' : 'hidden' }} lg:flex bg-[#F7FCFC] p-6 flex-col {{ $selected ? '' : 'items-center justify-center' }}">
                @if($selected)
                    {{-- Back to list (mobile only) --}}
                    <a href="{{ route('notifications.index', request()->except(['selected', 'page'])) }}"
                        class="lg:hidden inline-flex items-center gap-1.5 text-[13px] font-semibold text-[#156F8C] mb-4 -ml-1 px-1 py-1">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                        Back to notifications
                    </a>

                    {{-- Panel header --}}
                    <div class="flex items-center justify-between mb-5">
                        <p class="text-[14px] font-semibold text-[#1F2937]">{{ $selected->title }}</p>
                        <span class="text-[11px] text-[#64748B]">{{ $selected->created_at->diffForHumans() }}</span>
                    </div>

                    {{-- Notification message --}}
                    <p class="text-[13px] text-[#64748B] leading-relaxed mb-5">{{ $selected->message }}</p>

                    {{-- Contextual detail based on notifiable type --}}
                    @if($selected->notifiable && $selected->notifiable_type === 'App\Models\Review')
                        @php
    $review = $selected->notifiable;
@endphp

                        {{-- Property mini card --}}
                        @if($review->property)
                            <div class="bg-white border border-[#E2E8F0] rounded-xl overflow-hidden mb-5">
                                @if($review->property->media->isNotEmpty())
                                    <img src="{{ $review->property->media->first()->media_url }}"
                                        alt="{{ $review->property->title }}"
                                        class="w-full h-[140px] object-cover">
                                @endif
                                <div class="p-3">
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-[#156F8C]">{{ $review->property->property_type }}</p>
                                    <p class="text-[13px] font-semibold text-[#1F2937] mt-0.5">{{ $review->property->title }}</p>
                                    <p class="text-[11px] text-[#64748B] mt-0.5 flex items-center gap-1">
                                        <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        {{ $review->property->address }}
                                    </p>
                                </div>
                            </div>
                        @endif

                        {{-- Review content --}}
                        <div class="bg-white border border-[#E2E8F0] rounded-xl p-4 mb-5">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-8 h-8 rounded-full bg-[#2AA7A1] flex items-center justify-center text-white text-[11px] font-bold flex-shrink-0">
                                    {{ strtoupper(substr($review->tenant->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($review->tenant->last_name ?? '', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-[13px] font-semibold text-[#1F2937]">
                                        {{ ($review->tenant->first_name ?? '') . ' ' . ($review->tenant->last_name ?? '') }}
                                    </p>
                                    <p class="text-[11px] text-[#64748B]">Tenant</p>
                                </div>
                            </div>
                            {{-- Stars --}}
                            <div class="flex items-center gap-0.5 mb-2">
                                @for($i = 1; $i <= 5; $i++)
                                    <svg class="w-4 h-4" fill="{{ $i <= $review->rating ? '#FBBF24' : '#E2E8F0' }}" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                @endfor
                                <span class="text-[13px] font-semibold text-[#1F2937] ml-1.5">{{ number_format($review->rating, 1) }}</span>
                            </div>
                            @if($review->review_comment)
                                <p class="text-[13px] text-[#1F2937] leading-relaxed">{{ $review->review_comment }}</p>
                            @else
                                <p class="text-[13px] text-[#64748B] italic">No comment.</p>
                            @endif

                            {{-- Landlord reply --}}
                            @if($review->landlord_reply)
                                <div class="mt-3 pt-3 border-t border-[#E2E8F0]">
                                    <p class="text-[11px] font-semibold text-[#156F8C] mb-1">Landlord reply</p>
                                    <p class="text-[13px] text-[#1F2937] leading-relaxed">{{ $review->landlord_reply }}</p>
                                </div>
                            @endif
                        </div>

                        {{-- Actions --}}
                        <div class="flex flex-col gap-2 mt-auto">
                            <a href="{{ route('properties.show', $review->property_id) }}"
                                class="w-full py-2.5 text-center rounded-xl bg-[#2AA7A1] text-white text-[13px] font-semibold hover:brightness-95 transition-all">
                                View property
                            </a>
                        </div>
                    @else
                        {{-- Generic notification (no linked entity) --}}
                        <div class="bg-white border border-[#E2E8F0] rounded-xl p-4 text-center">
                            <p class="text-[13px] text-[#64748B]">No additional details available for this notification.</p>
                        </div>
                    @endif
                @else
                    {{-- Empty state: no notification selected --}}
                    <div class="text-center">
                        <div class="w-14 h-14 rounded-2xl bg-white border border-[#E2E8F0] flex items-center justify-center mx-auto mb-4">
                            <svg class="w-7 h-7 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672L13.684 16.6m0 0l-2.51 2.225.569-9.47 5.227 7.917-3.286-.672zM12 2.25V4.5m5.834.166l-1.591 1.591M20.25 10.5H18M7.757 14.743l-1.59 1.59M6 10.5H3.75m4.007-4.243l-1.59-1.59" />
                            </svg>
                        </div>
                        <p class="text-[14px] font-semibold text-[#1F2937]">Select a notification</p>
                        <p class="text-[13px] text-[#64748B] mt-1">Click on a notification to see details.</p>
                    </div>
                @endif
            </div>

        </div>

        @if($notifications->hasPages())
            <div class="mt-5 flex justify-center">{{ $notifications->links() }}</div>
        @endif
    @endif

</div>
@endsection