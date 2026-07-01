@extends(auth()->user()->hasRole('Landlord') && !auth()->user()->hasRole('Admin') ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-4xl mx-auto px-4 py-10">

        <!-- Inbox Header Title -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Messages</h1>
                <p class="text-sm text-gray-500 mt-1">Manage your active inquiries and conversation threads regarding
                    properties.</p>
            </div>
        </div>

        <!-- Status Tabs -->
        <div class="flex items-center gap-2 mb-6">
            @php
                $tabs = [
                    'all' => ['label' => 'All', 'count' => $allCount],
                    'unread' => ['label' => 'Unread', 'count' => $unreadCount],
                    'resolved' => ['label' => 'Resolved', 'count' => $resolvedCount],
                ];
            @endphp
            @foreach ($tabs as $key => $tab)
                <a href="{{ route('conversations.index', array_filter(['status' => $key, 'search' => request('search')])) }}"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold transition
                                {{ $status === $key ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' }}">
                    {{ $tab['label'] }}
                    @if($tab['count'] > 0)
                        <span class="text-xs font-bold px-1.5 py-0.5 rounded-full
                                        {{ $status === $key ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500' }}">
                            {{ $tab['count'] }}
                        </span>
                    @endif
                </a>
            @endforeach
        </div>

        <!-- Search Bar -->
        <form method="GET" action="{{ route('conversations.index') }}"
            class="flex gap-3 mb-6 p-4 bg-white border border-gray-100 rounded-2xl shadow-sm">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none"
                    stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Search by person or property name…"
                    class="w-full pl-9 pr-4 py-2.5 text-sm text-gray-800 bg-[#F7F8FA] border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#286CD2]/30 focus:border-[#286CD2] transition placeholder-gray-400" />
            </div>
            <button type="submit"
                class="px-5 py-2.5 text-sm font-bold text-white bg-[#286CD2] hover:bg-[#1e57b0] rounded-xl shadow-sm transition-all duration-200">
                Search
            </button>
            @if(request('search'))
                <a href="{{ route('conversations.index') }}"
                    class="px-4 py-2.5 text-sm font-semibold text-gray-500 bg-[#F7F8FA] border border-gray-200 hover:bg-gray-100 rounded-xl transition-all duration-200">
                    Clear
                </a>
            @endif
        </form>

        <!-- Unified Inbox Container -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            @forelse ($conversations as $conversation)
                @php
                    $otherParty = auth()->id() === $conversation->tenant_id
                        ? $conversation->landlord
                        : $conversation->tenant;
                @endphp

                <a href="{{ route('conversations.show', $conversation) }}"
                    class="group block border-b border-gray-100 last:border-b-0 p-5 hover:bg-blue-50/20 transition duration-200">
                    <div class="flex items-center justify-between gap-4">

                        <!-- Left Section: Avatar, Name, & Subtext -->
                        <div class="flex items-center space-x-4 min-w-0 flex-1">
                            <div
                                class="w-12 h-12 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-base shadow-sm shrink-0 transition group-hover:scale-105">
                                {{ strtoupper(substr($otherParty->first_name, 0, 1)) }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <h2 class="font-bold text-gray-900 text-base group-hover:text-blue-600 transition truncate">
                                    {{ $otherParty->first_name }} {{ $otherParty->last_name }}
                                </h2>
                                <p class="text-sm text-gray-500 mt-1 truncate font-medium">
                                    {{ $conversation->latestMessage->message ?? 'No messages shared yet.' }}
                                </p>
                            </div>
                        </div>

                        <!-- Right Section: Attached Property Context Tag & Chevron -->
                        <div class="flex items-center space-x-4 shrink-0">
                            <div class="text-right hidden sm:block">
                                <span
                                    class="inline-flex items-center px-3 py-1.5 rounded-xl text-xs font-semibold bg-gray-50 text-gray-600 border border-gray-100 max-w-[220px] truncate">
                                    <svg class="w-3.5 h-3.5 text-gray-400 mr-2 shrink-0" fill="none" stroke="currentColor"
                                        stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                                    </svg>
                                    {{ $conversation->property->title }}
                                </span>
                            </div>

                            <svg class="w-5 h-5 text-gray-300 group-hover:text-blue-500 transform group-hover:translate-x-1 transition"
                                fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                            </svg>
                        </div>

                    </div>
                </a>
            @empty
                <div class="p-16 text-center">
                    <div
                        class="w-16 h-16 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 border border-blue-100">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a.75.75 0 01-1.074-.765 1.65 1.65 0 00.33-1.223 8.254 8.254 0 01-2.414-5.732C2.25 7.444 6.282 3.75 11 3.75c4.718 0 8.5 3.694 8.5 8.25z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-gray-900">No conversations yet</h3>
                    <p class="text-sm text-gray-500 mt-1 max-w-sm mx-auto">When you drop inquiries to listing landlords, your
                        real-time secure room interactions will manifest here.</p>
                </div>
            @endforelse
        </div>
    </div>
@endsection