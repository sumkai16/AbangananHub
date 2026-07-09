<x-app-layout>

    {{-- ══════════════════════════════════════════
    MAIN BODY
    ══════════════════════════════════════════ --}}
    <div class="w-full px-4 sm:px-6 lg:px-[50px] py-10 pb-16">

        {{-- HERO --}}
        <div
            class="rounded-3xl mb-10 bg-gradient-to-br from-[#1A3A6E] via-[#2AA7A1] to-[#2AA7A1] p-6 sm:p-10 md:p-12 flex flex-col md:flex-row items-start md:items-center justify-between gap-7 relative overflow-hidden shadow-[0_8px_32px_rgba(40,108,210,0.28)]">
            <div
                class="absolute right-[-20px] bottom-[-40px] w-64 h-64 rounded-full bg-white opacity-5 pointer-events-none">
            </div>

            <div class="relative z-10">
                <div
                    class="inline-block text-[10.5px] font-bold tracking-widest uppercase text-white/70 mb-3 bg-white/10 px-3 py-1 rounded-full">
                    🏡 Cebu's Verified Rental Platform
                </div>
                <h1 class="text-[28px] md:text-3xl font-black text-white leading-tight tracking-tight mb-2">
                    Welcome back,<br>{{ auth()->user()->first_name }}!
                </h1>
                <p class="text-sm text-white/80 leading-relaxed max-w-[420px]">
                    Find safe, verified accommodations across Cebu. Every listing reviewed. Every landlord checked.
                </p>
            </div>

            <div
                class="flex flex-col md:flex-row items-start md:items-center gap-3 flex-shrink-0 relative z-10 w-full md:w-auto">
                <a href="{{ route('properties.index') }}"
                    class="h-12 px-7 bg-white text-[#156F8C] rounded-xl text-[14.5px] font-extrabold flex items-center gap-2 hover:-translate-y-0.5 hover:shadow-lg transition-all whitespace-nowrap shadow-md">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    Browse Properties
                </a>
                <a href="{{ route('reservations.index') }}"
                    class="h-12 px-7 bg-white/15 text-white rounded-xl text-[14.5px] font-bold flex items-center gap-2 border border-white/35 backdrop-blur-sm hover:bg-white/25 hover:border-white/60 hover:-translate-y-0.5 transition-all whitespace-nowrap">
                    View My Reservations
                </a>
            </div>
        </div>

        {{-- STAT CARDS --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-6 mb-12">
            <x-stat-card label="Upcoming Stays" :value="$upcomingCount" sub="Active reservations" color="#2AA7A1"
                bgColor="#EBF3FF">
                <x-slot name="icon">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </x-slot>
            </x-stat-card>

            <x-stat-card label="Messages" :value="$messagesCount" sub="Unread threads" color="#7C3AED"
                bgColor="#F3E8FF">
                <x-slot name="icon">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#7C3AED" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                    </svg>
                </x-slot>
            </x-stat-card>

            <x-stat-card label="Saved Listings" :value="$savedCount" sub="In your favorites" color="#059669"
                bgColor="#ECFDF5">
                <x-slot name="icon">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                </x-slot>
            </x-stat-card>

            <x-stat-card label="Open Reports" :value="$reportsCount" sub="Pending resolution" color="#E11D48"
                bgColor="#FFF1F2">
                <x-slot name="icon">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#E11D48" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                    </svg>
                </x-slot>
            </x-stat-card>
        </div>

        {{-- RESERVATIONS --}}
        <div>
            <x-section-header title="My Reservations" sub="Track your current and upcoming bookings in Cebu"
                :href="route('reservations.index')" />

            <div class="flex flex-wrap items-center gap-3 mb-6">
                <div class="flex-1 min-w-[200px] relative">
                    <svg class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" width="16" height="16"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="text" id="reservation-search"
                        class="w-full h-12 border border-gray-200 rounded-full pl-11 pr-5 text-[14px] text-gray-800 bg-white focus:border-gray-800 focus:ring-1 focus:ring-gray-800 transition-all outline-none"
                        placeholder="Search by property or reference…">
                </div>
                <select id="reservation-filter"
                    class="h-12 px-5 border border-gray-200 rounded-full text-[14px] font-semibold text-gray-700 bg-white focus:border-gray-800 focus:ring-1 focus:ring-gray-800 transition-all outline-none appearance-none cursor-pointer pr-10 relative">
                    <option value="all">All Statuses</option>
                    <option value="Approved">Approved</option>
                    <option value="Pending">Pending</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>

            <div class="flex flex-col gap-4" id="reservations-list">
                @forelse($reservations ?? [] as $reservation)
                    <x-reservation-card :reservation="$reservation" />
                @empty
                    <x-empty-state title="No reservations yet"
                        message="Browse verified properties across Cebu and make your first booking today."
                        :href="route('properties.index')" cta="Start exploring">
                        <x-slot name="icon">
                            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </x-slot>
                    </x-empty-state>
                @endforelse
            </div>
        </div>

        {{-- TWO-COL --}}
        <div class="grid grid-cols-1 md:grid-cols-[1fr_360px] gap-8 mt-12">

            {{-- LEFT: Quick Actions + Activity --}}
            <div>
                <x-section-header title="Quick Actions" />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-10">
                    <a href="{{ route('properties.index') }}"
                        class="flex items-center gap-4 p-4 rounded-[16px] border border-gray-200 bg-white hover:border-gray-800 hover:shadow-md transition-all group">
                        <div
                            class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 group-hover:bg-gray-800 group-hover:text-white transition-colors">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <span class="text-[15px] font-semibold text-gray-800">Search Properties</span>
                    </a>
                    <a href="{{ route('favorites.index') }}"
                        class="flex items-center gap-4 p-4 rounded-[16px] border border-gray-200 bg-white hover:border-gray-800 hover:shadow-md transition-all group">
                        <div
                            class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 group-hover:bg-gray-800 group-hover:text-white transition-colors">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        </div>
                        <span class="text-[15px] font-semibold text-gray-800">Saved Favorites</span>
                    </a>
                    <a href="{{ route('conversations.index') }}"
                        class="flex items-center gap-4 p-4 rounded-[16px] border border-gray-200 bg-white hover:border-gray-800 hover:shadow-md transition-all group">
                        <div
                            class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 group-hover:bg-gray-800 group-hover:text-white transition-colors">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                            </svg>
                        </div>
                        <span class="text-[15px] font-semibold text-gray-800">Open Messages</span>
                    </a>
                    <a href="{{ route('profile.edit') }}"
                        class="flex items-center gap-4 p-4 rounded-[16px] border border-gray-200 bg-white hover:border-gray-800 hover:shadow-md transition-all group">
                        <div
                            class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 group-hover:bg-gray-800 group-hover:text-white transition-colors">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <span class="text-[15px] font-semibold text-gray-800">Account Settings</span>
                    </a>
                </div>

                <x-section-header title="Recent Activity" />

                <div class="bg-white border border-gray-200 rounded-[20px] p-6 shadow-sm">
                    @forelse($recentActivity ?? [] as $activity)
                        <div class="flex items-start gap-4 py-3.5 border-b border-gray-100 last:border-0 last:pb-0">
                            <div class="w-2 h-2 rounded-full bg-[#2AA7A1] flex-shrink-0 mt-2"></div>
                            <div class="flex-1 text-[14px] text-gray-700 leading-relaxed">
                                {!! preg_replace('/(\w+)/', '<strong class="text-gray-900">$1</strong>', $activity->message, 1) !!}
                            </div>
                            <div class="text-[12px] text-gray-400 font-medium whitespace-nowrap mt-0.5">
                                {{ $activity->created_at->diffForHumans() }}
                            </div>
                        </div>
                    @empty
                        <div class="flex items-start gap-4 py-3.5 border-b border-gray-100 last:border-0 last:pb-0">
                            <div class="w-2 h-2 rounded-full bg-[#2AA7A1] flex-shrink-0 mt-2"></div>
                            <div class="flex-1 text-[14px] text-gray-700 leading-relaxed">
                                Welcome to <strong class="text-gray-900">AbangananHub</strong>! Start by browsing verified
                                properties.
                            </div>
                            <div class="text-[12px] text-gray-400 font-medium whitespace-nowrap mt-0.5">Just now</div>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- RIGHT: Become a Landlord card (hidden if already Landlord) --}}
            @if(!auth()->user()->hasRole('Landlord'))
                <div>
                    <div
                        class="rounded-[24px] p-8 bg-gradient-to-br from-[#156F8C] to-[#156F8C] relative overflow-hidden shadow-[0_8px_30px_rgba(40,108,210,0.3)] text-white">
                        <div class="absolute -right-8 -top-8 w-36 h-36 rounded-full bg-white opacity-10"></div>
                        <div class="absolute -left-5 -bottom-8 w-[110px] h-[110px] rounded-full bg-white opacity-5"></div>

                        <h3 class="text-[22px] font-extrabold mb-3 leading-[1.2] relative z-10">
                            Become a Host
                        </h3>
                        <p class="text-[14px] text-white/80 leading-[1.6] mb-6 relative z-10">
                            Earn extra income and unlock new opportunities by listing your space on AbangananHub.
                        </p>

                        <a href="#"
                            class="inline-flex items-center justify-center w-full py-3.5 bg-white text-[#e00b41] rounded-xl text-[15px] font-bold shadow-md hover:bg-gray-50 transition-all relative z-10">
                            Learn more
                        </a>
                    </div>
                </div>
            @endif

        </div>{{-- end grid --}}
    </div>

    @push('scripts')
        <script>
            (function () {
                // Reservation client-side filter + search
                const filter = document.getElementById('reservation-filter');
                const search = document.getElementById('reservation-search');
                const rows = document.querySelectorAll('#reservations-list .reservation-row');

                function applyFilters() {
                    const status = filter ? filter.value : 'all';
                    const query = search ? search.value.toLowerCase().trim() : '';
                    rows.forEach(row => {
                        const matchStatus = status === 'all' || row.dataset.status === status;
                        const title = row.querySelector('.property-title')?.textContent.toLowerCase() || '';
                        const ref = row.querySelector('.reference-id')?.textContent.toLowerCase() || '';
                        const matchQuery = !query || title.includes(query) || ref.includes(query);
                        row.style.display = (matchStatus && matchQuery) ? '' : 'none';
                    });
                }

                if (filter) filter.addEventListener('change', applyFilters);
                if (search) search.addEventListener('input', applyFilters);
            })();
        </script>
    @endpush
</x-app-layout>