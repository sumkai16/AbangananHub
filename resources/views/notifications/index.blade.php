@php
    $searchBar = false;
@endphp
@extends('layouts.app')

@section('content')
<div class="max-w-[1200px] mx-auto px-4 sm:px-6 lg:px-8 py-10">

    {{-- Header --}}
    <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
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
    <div class="flex flex-wrap items-center gap-1.5 mb-5">
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
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_400px] gap-0 bg-white border border-[#E2E8F0] rounded-2xl overflow-hidden shadow-sm min-h-[500px]">

            {{-- LEFT: Notification list --}}
            <div class="{{ $selected ? 'hidden' : 'flex' }} lg:flex border-r border-[#E2E8F0] flex-col divide-y divide-[#E2E8F0] overflow-y-auto max-h-[700px]">
                @foreach($notifications as $n)
                    @php
                        $isSelected = $selected && $selected->notification_id === $n->notification_id;
                        $isReview = $n->notifiable_type === 'App\Models\Review' && $n->notifiable;
                        $review = $isReview ? $n->notifiable : null;

                        // Determine who triggered this notification
                        if ($isReview) {
                            // For "Landlord Reply" — the landlord is the actor
                            // For "New Review" — the tenant is the actor
                            if (str_contains($n->title, 'Reply')) {
                                $actorName = trim(($review->landlord->first_name ?? '') . ' ' . ($review->landlord->last_name ?? ''));
                                $actorInitials = strtoupper(substr($review->landlord->first_name ?? '?', 0, 1)) . strtoupper(substr($review->landlord->last_name ?? '', 0, 1));
                                $actorRole = 'Landlord of ' . ($review->property->title ?? 'a property');
                            } else {
                                $actorName = trim(($review->tenant->first_name ?? '') . ' ' . ($review->tenant->last_name ?? ''));
                                $actorInitials = strtoupper(substr($review->tenant->first_name ?? '?', 0, 1)) . strtoupper(substr($review->tenant->last_name ?? '', 0, 1));
                                $actorRole = 'Reviewed ' . ($review->property->title ?? 'a property');
                            }
                        }

                        // Fallback icon for non-review notifications
                        $iconConfig = match(true) {
                            str_contains($n->title, 'Approved') || str_contains($n->title, 'approved') => ['bg' => 'bg-[#22C55E]', 'icon' => 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                            str_contains($n->title, 'Rejected') || str_contains($n->title, 'rejected') || str_contains($n->title, 'Cancel') => ['bg' => 'bg-[#EF4444]', 'icon' => 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                            str_contains($n->title, 'Reservation') || str_contains($n->title, 'reservation') => ['bg' => 'bg-[#2AA7A1]', 'icon' => 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5'],
                            default => ['bg' => 'bg-[#156F8C]', 'icon' => 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0'],
                        };
                    @endphp
                    <a href="{{ route('notifications.index', array_merge(request()->except(['selected', 'page']), ['selected' => $n->notification_id])) }}"
                        class="block p-4 transition-all duration-200 hover:bg-[#EEF8F8] {{ $isSelected ? 'bg-[#EEF8F8] border-l-[3px] border-l-[#2AA7A1]' : 'border-l-[3px] border-l-transparent' }} {{ $n->is_read && !$isSelected ? 'opacity-70' : '' }}">
                        <div class="flex items-center gap-3">
                            @if($isReview)
                                {{-- Avatar with initials --}}
                                <div class="w-10 h-10 rounded-full bg-[#2AA7A1] flex items-center justify-center text-white text-[12px] font-semibold flex-shrink-0">
                                    {{ $actorInitials }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <p class="text-[13px] font-semibold text-[#1F2937] truncate">{{ $actorName }}</p>
                                        @if(!$n->is_read)
                                            <span class="w-1.5 h-1.5 rounded-full bg-[#2AA7A1] flex-shrink-0"></span>
                                        @endif
                                    </div>
                                    <p class="text-[12px] text-[#64748B] mt-0.5 truncate">{{ $actorRole }}</p>
                                    <p class="text-[11px] text-[#64748B]/70 mt-1">{{ $n->created_at->diffForHumans() }}</p>
                                </div>
                            @else
                                {{-- Icon-based fallback for non-review notifications --}}
                                <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 {{ $iconConfig['bg'] }}">
                                    <svg class="w-4.5 h-4.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            {{-- RIGHT: Detail panel --}}
            <div class="{{ $selected ? 'flex' : 'hidden' }} lg:flex bg-[#F7FCFC] flex-col {{ $selected ? '' : 'items-center justify-center' }}">
                @if($selected)
                    @php
                        $selIsReview = $selected->notifiable_type === 'App\Models\Review' && $selected->notifiable;
                        $selReview = $selIsReview ? $selected->notifiable : null;

                        if ($selIsReview) {
                            if (str_contains($selected->title, 'Reply')) {
                                $panelActorName = trim(($selReview->landlord->first_name ?? '') . ' ' . ($selReview->landlord->last_name ?? ''));
                                $panelActorInitials = strtoupper(substr($selReview->landlord->first_name ?? '?', 0, 1)) . strtoupper(substr($selReview->landlord->last_name ?? '', 0, 1));
                                $panelActorRole = 'Landlord of ' . ($selReview->property->title ?? 'a property');
                            } else {
                                $panelActorName = trim(($selReview->tenant->first_name ?? '') . ' ' . ($selReview->tenant->last_name ?? ''));
                                $panelActorInitials = strtoupper(substr($selReview->tenant->first_name ?? '?', 0, 1)) . strtoupper(substr($selReview->tenant->last_name ?? '', 0, 1));
                                $panelActorRole = 'Reviewed ' . ($selReview->property->title ?? 'a property');
                            }
                        }
                    @endphp

                    {{-- Back to list (mobile only) --}}
                    <a href="{{ route('notifications.index', request()->except(['selected', 'page'])) }}"
                        class="lg:hidden flex items-center gap-1.5 text-[13px] font-semibold text-[#156F8C] px-6 pt-5 -mb-1">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                        Back to notifications
                    </a>

                    {{-- Panel header --}}
                    <div class="px-6 py-4 border-b border-[#E2E8F0] bg-white">
                        @if($selIsReview)
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-[#2AA7A1] flex items-center justify-center text-white text-[11px] font-bold flex-shrink-0">
                                    {{ $panelActorInitials }}
                                </div>
                                <div>
                                    <p class="text-[14px] font-bold text-[#1F2937]">{{ $panelActorName }}</p>
                                    <p class="text-[12px] text-[#64748B]">{{ $panelActorRole }}</p>
                                </div>
                            </div>
                        @else
                            <p class="text-[14px] font-bold text-[#1F2937]">{{ $selected->title }}</p>
                            <p class="text-[11px] text-[#64748B] mt-1">{{ $selected->created_at->format('M d, Y \a\t g:i A') }}</p>
                        @endif
                    </div>

                    <div class="p-6 flex-1 overflow-y-auto flex flex-col gap-3">

                        @if($selIsReview)
                            {{-- Original review card --}}
                            <div class="bg-white border border-[#E2E8F0] rounded-xl overflow-hidden">
                                <div class="px-4 py-3">
                                    <p class="text-[10px] font-bold uppercase tracking-wider text-[#64748B] mb-2">
                                        {{ $selReview->tenant_id === auth()->id() ? 'Your review' : 'Tenant review' }}
                                    </p>
                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="w-8 h-8 rounded-full bg-[#2AA7A1] flex items-center justify-center text-white text-[11px] font-bold flex-shrink-0">
                                            {{ strtoupper(substr($selReview->tenant->first_name ?? '?', 0, 1)) }}{{ strtoupper(substr($selReview->tenant->last_name ?? '', 0, 1)) }}
                                        </div>
                                        <div>
                                            <p class="text-[13px] font-semibold text-[#1F2937]">
                                                {{ ($selReview->tenant->first_name ?? '') . ' ' . ($selReview->tenant->last_name ?? '') }}
                                            </p>
                                            <div class="flex items-center gap-0.5 mt-0.5">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <svg class="w-3.5 h-3.5" fill="{{ $i <= $selReview->rating ? '#FBBF24' : '#E2E8F0' }}" viewBox="0 0 20 20">
                                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                                    </svg>
                                                @endfor
                                                <span class="text-[12px] font-semibold text-[#1F2937] ml-1">{{ number_format($selReview->rating, 1) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    @if($selReview->review_comment)
                                        <p class="text-[13px] text-[#1F2937] leading-relaxed">{{ $selReview->review_comment }}</p>
                                    @else
                                        <p class="text-[13px] text-[#64748B] italic">No comment provided.</p>
                                    @endif
                                    <p class="text-[11px] text-[#64748B]/70 mt-2">{{ $selReview->created_at->format('M d, Y') }}</p>
                                </div>
                            </div>

                            {{-- Landlord reply card --}}
                            @if($selReview->landlord_reply)
                                <div class="bg-white border border-[#E2E8F0] rounded-xl overflow-hidden">
                                    <div class="px-4 py-3">
                                        <div class="flex items-center gap-2 mb-2">
                                            <svg class="w-3.5 h-3.5 text-[#156F8C]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                                            </svg>
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-[#156F8C]">Landlord reply</p>
                                        </div>
                                        <p class="text-[13px] text-[#1F2937] leading-relaxed">{{ $selReview->landlord_reply }}</p>
                                        @if($selReview->landlord_replied_at)
                                            <p class="text-[11px] text-[#64748B]/70 mt-2">{{ \Carbon\Carbon::parse($selReview->landlord_replied_at)->format('M d, Y \a\t g:i A') }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Property link --}}
                            @if($selReview->property)
                                <a href="{{ route('properties.show', $selReview->property_id) }}"
                                    class="flex items-center gap-3 p-3 bg-white border border-[#E2E8F0] rounded-xl hover:brightness-95 transition-all mt-auto">
                                    @if($selReview->property->media->isNotEmpty())
                                        <img src="{{ $selReview->property->media->first()->media_url }}"
                                            alt="{{ $selReview->property->title }}"
                                            class="w-12 h-12 rounded-lg object-cover flex-shrink-0">
                                    @else
                                        <div class="w-12 h-12 rounded-lg bg-[#EEF8F8] flex items-center justify-center flex-shrink-0">
                                            <svg class="w-5 h-5 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                                            </svg>
                                        </div>
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#156F8C]">{{ $selReview->property->property_type }}</p>
                                        <p class="text-[13px] font-semibold text-[#1F2937] truncate">{{ $selReview->property->title }}</p>
                                    </div>
                                    <svg class="w-4 h-4 text-[#64748B] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                                    </svg>
                                </a>
                            @endif

                        @else
                            {{-- Generic notification (no linked entity) --}}
                            <div class="bg-white border border-[#E2E8F0] rounded-xl p-4 mb-3">
                                <p class="text-[13px] text-[#1F2937] leading-relaxed">{{ $selected->message }}</p>
                                <p class="text-[11px] text-[#64748B]/70 mt-2">{{ $selected->created_at->format('M d, Y \a\t g:i A') }}</p>
                            </div>

                            <div class="flex-1 flex flex-col items-center justify-center text-center py-6">
                                <div class="w-12 h-12 rounded-2xl bg-[#EEF8F8] border border-[#E2E8F0] flex items-center justify-center mb-3">
                                    <svg class="w-6 h-6 text-[#64748B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                    </svg>
                                </div>
                                <p class="text-[13px] font-semibold text-[#1F2937]">That's everything for this one</p>
                                <p class="text-[12px] text-[#64748B] mt-1 max-w-[220px]">This notification doesn't have any linked details to show.</p>
                            </div>
                        @endif
                    </div>

                @else
                    {{-- Empty state: no notification selected --}}
                    <div class="text-center px-6">
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