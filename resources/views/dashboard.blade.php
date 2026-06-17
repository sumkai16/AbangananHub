<x-app-layout>
    {{-- ══════════════════════════════════════════
    MAIN BODY
    ══════════════════════════════════════════ --}}
    <div class="max-w-7xl mx-auto px-6 py-10 pb-16">

        {{-- HERO --}}
        <div class="rounded-3xl mb-10 bg-gradient-to-br from-[#1A3A6E] via-[#286CD2] to-[#61B2F0] p-10 md:p-12 flex flex-col md:flex-row items-start md:items-center justify-between gap-7 relative overflow-hidden shadow-[0_8px_32px_rgba(40,108,210,0.28)]">
            <div class="absolute right-[-20px] bottom-[-40px] w-64 h-64 rounded-full bg-white opacity-5 pointer-events-none"></div>
            
            <div class="relative z-10">
                <div class="inline-block text-[10.5px] font-bold tracking-widest uppercase text-white/70 mb-3 bg-white/10 px-3 py-1 rounded-full">
                    🏡 Cebu's Verified Rental Platform
                </div>
                <h1 class="text-[28px] md:text-3xl font-black text-white leading-tight tracking-tight mb-2">
                    Welcome back,<br>{{ auth()->user()->first_name }}!
                </h1>
                <p class="text-sm text-white/80 leading-relaxed max-w-[420px]">
                    Find safe, verified accommodations across Cebu. Every listing reviewed. Every landlord checked.
                </p>
            </div>
            
            <div class="flex flex-col md:flex-row items-start md:items-center gap-3 flex-shrink-0 relative z-10 w-full md:w-auto">
                <a href="{{ route('properties.index') }}" class="h-12 px-7 bg-white text-[#286CD2] rounded-xl text-[14.5px] font-extrabold flex items-center gap-2 hover:-translate-y-0.5 hover:shadow-lg transition-all whitespace-nowrap shadow-md">
                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    Browse Properties
                </a>
                <a href="{{ route('reservations.index') }}" class="h-12 px-7 bg-white/15 text-white rounded-xl text-[14.5px] font-bold flex items-center gap-2 border border-white/35 backdrop-blur-sm hover:bg-white/25 hover:border-white/60 hover:-translate-y-0.5 transition-all whitespace-nowrap">
                    View My Reservations
                </a>
            </div>
        </div>

        {{-- STAT CARDS (Airbnb Style) --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-12">
            <div class="bg-white rounded-[24px] p-6 shadow-[0_4px_24px_rgba(0,0,0,0.06)] hover:shadow-[0_8px_32px_rgba(0,0,0,0.1)] transition-all relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-32 h-32 rounded-full bg-[#EBF3FF] opacity-80 group-hover:scale-110 transition-transform"></div>
                <div class="w-12 h-12 rounded-[14px] bg-[#EBF3FF] flex items-center justify-center mb-6 relative z-10">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#286CD2" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="relative z-10">
                    <div class="text-[12px] font-bold text-gray-500 uppercase tracking-widest mb-2">Upcoming Stays</div>
                    <div class="text-4xl font-black text-[#286CD2] leading-none mb-2">{{ $upcomingCount ?? 0 }}</div>
                    <div class="text-[13px] text-gray-400 font-medium">Active reservations</div>
                </div>
            </div>
            
            <div class="bg-white rounded-[24px] p-6 shadow-[0_4px_24px_rgba(0,0,0,0.06)] hover:shadow-[0_8px_32px_rgba(0,0,0,0.1)] transition-all relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-32 h-32 rounded-full bg-[#F3E8FF] opacity-80 group-hover:scale-110 transition-transform"></div>
                <div class="w-12 h-12 rounded-[14px] bg-[#F3E8FF] flex items-center justify-center mb-6 relative z-10">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#7C3AED" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                    </svg>
                </div>
                <div class="relative z-10">
                    <div class="text-[12px] font-bold text-gray-500 uppercase tracking-widest mb-2">Messages</div>
                    <div class="text-4xl font-black text-[#7C3AED] leading-none mb-2">{{ $messagesCount ?? 0 }}</div>
                    <div class="text-[13px] text-gray-400 font-medium">Unread threads</div>
                </div>
            </div>
            
            <div class="bg-white rounded-[24px] p-6 shadow-[0_4px_24px_rgba(0,0,0,0.06)] hover:shadow-[0_8px_32px_rgba(0,0,0,0.1)] transition-all relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-32 h-32 rounded-full bg-[#ECFDF5] opacity-80 group-hover:scale-110 transition-transform"></div>
                <div class="w-12 h-12 rounded-[14px] bg-[#ECFDF5] flex items-center justify-center mb-6 relative z-10">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#059669" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                    </svg>
                </div>
                <div class="relative z-10">
                    <div class="text-[12px] font-bold text-gray-500 uppercase tracking-widest mb-2">Saved Listings</div>
                    <div class="text-4xl font-black text-[#059669] leading-none mb-2">{{ $savedCount ?? 0 }}</div>
                    <div class="text-[13px] text-gray-400 font-medium">In your favorites</div>
                </div>
            </div>
            
            <div class="bg-white rounded-[24px] p-6 shadow-[0_4px_24px_rgba(0,0,0,0.06)] hover:shadow-[0_8px_32px_rgba(0,0,0,0.1)] transition-all relative overflow-hidden group">
                <div class="absolute -right-6 -top-6 w-32 h-32 rounded-full bg-[#FFF1F2] opacity-80 group-hover:scale-110 transition-transform"></div>
                <div class="w-12 h-12 rounded-[14px] bg-[#FFF1F2] flex items-center justify-center mb-6 relative z-10">
                    <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#E11D48" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                    </svg>
                </div>
                <div class="relative z-10">
                    <div class="text-[12px] font-bold text-gray-500 uppercase tracking-widest mb-2">Support Tickets</div>
                    <div class="text-4xl font-black text-[#E11D48] leading-none mb-2">{{ $reportsCount ?? 0 }}</div>
                    <div class="text-[13px] text-gray-400 font-medium">Open cases</div>
                </div>
            </div>
        </div>

        {{-- RESERVATIONS --}}
        <div>
            <div class="flex items-end justify-between mb-6">
                <div>
                    <h2 class="text-[22px] font-extrabold text-[#1A1A2E] tracking-tight">My Reservations</h2>
                    <p class="text-[14px] text-gray-500 mt-1">Track your current and upcoming bookings in Cebu</p>
                </div>
                <a href="{{ route('reservations.index') }}" class="text-[14px] font-semibold text-[#286CD2] px-4 py-2 border border-blue-200 rounded-full hover:bg-blue-50 transition-colors">
                    View all
                </a>
            </div>

            <div class="flex flex-wrap items-center gap-3 mb-6">
                <div class="flex-1 min-w-[200px] relative">
                    <svg class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input type="text" id="reservation-search" class="w-full h-12 border border-gray-200 rounded-full pl-11 pr-5 text-[14px] text-gray-800 bg-white focus:border-gray-800 focus:ring-1 focus:ring-gray-800 transition-all outline-none" placeholder="Search by property or reference…">
                </div>
                <select id="reservation-filter" class="h-12 px-5 border border-gray-200 rounded-full text-[14px] font-semibold text-gray-700 bg-white focus:border-gray-800 focus:ring-1 focus:ring-gray-800 transition-all outline-none appearance-none cursor-pointer pr-10 relative">
                    <option value="all">All Statuses</option>
                    <option value="Approved">Approved</option>
                    <option value="Pending">Pending</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>

            <div class="flex flex-col gap-4" id="reservations-list">
                @forelse($reservations ?? [] as $reservation)
                    @php
                        $pillClass = match ($reservation->reservation_status) {
                            'Approved' => 'bg-emerald-100 text-emerald-800',
                            'Pending' => 'bg-amber-100 text-amber-800',
                            'Rejected' => 'bg-red-100 text-red-800',
                            default => 'bg-gray-100 text-gray-800',
                        };
                    @endphp
                    <div class="flex flex-col md:flex-row md:items-center gap-5 bg-white border border-gray-200 rounded-[20px] p-5 shadow-sm hover:shadow-md transition-all reservation-row" data-status="{{ $reservation->reservation_status }}">
                        <div class="w-[72px] h-[72px] rounded-[16px] flex-shrink-0 bg-gray-100 overflow-hidden flex items-center justify-center relative">
                            @if($reservation->property->media->first())
                                <img src="{{ Storage::url($reservation->property->media->first()->file_path) }}" class="w-full h-full object-cover" alt="Property">
                            @else
                                <div class="text-2xl font-bold text-gray-400">{{ strtoupper(substr($reservation->property->title ?? 'P', 0, 1)) }}</div>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[16px] font-bold text-[#1A1A2E] mb-1.5 truncate property-title">
                                {{ $reservation->property->title ?? 'Property' }}
                            </div>
                            <div class="text-[14px] text-gray-500">
                                <span class="font-semibold text-gray-700">{{ $reservation->reservation_date?->format('M d, Y') ?? 'TBD' }}</span>
                                @if($reservation->property->address ?? false)
                                    &nbsp;·&nbsp; {{ Str::limit($reservation->property->address, 42) }}
                                @endif
                            </div>
                        </div>
                        <div class="flex md:flex-col items-center justify-between md:items-end w-full md:w-auto mt-2 md:mt-0 text-right gap-2">
                            <div class="inline-block text-[12px] font-bold uppercase px-3 py-1 rounded-md {{ $pillClass }}">{{ $reservation->reservation_status }}</div>
                            <div class="text-[13px] text-gray-400 font-medium reference-id">Ref: #R{{ $reservation->id ?? $reservation->reservation_id }}</div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-16 px-8 border-2 border-dashed border-gray-200 rounded-[24px] bg-gray-50">
                        <div class="w-16 h-16 rounded-full bg-white flex items-center justify-center mx-auto mb-4 text-gray-400 shadow-sm border border-gray-100">
                            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div class="text-[16px] font-bold text-gray-800 mb-2">No reservations yet</div>
                        <div class="text-[14px] text-gray-500 mb-6">Browse verified properties across Cebu and make your first booking today.</div>
                        <a href="{{ route('properties.index') }}" class="inline-flex items-center gap-2 px-6 py-3 bg-[#e00b41] text-white rounded-lg text-[15px] font-bold shadow-md hover:bg-[#c00936] transition-colors">
                            Start exploring
                        </a>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- TWO-COL --}}
        <div class="grid grid-cols-1 md:grid-cols-[1fr_360px] gap-8 mt-12">

            {{-- LEFT: Quick Actions + Activity --}}
            <div>
                <div class="mb-5">
                    <h2 class="text-[20px] font-bold text-gray-900 tracking-tight">Quick Actions</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-10">
                    <a href="{{ route('properties.index') }}" class="flex items-center gap-4 p-4 rounded-[16px] border border-gray-200 bg-white hover:border-gray-800 hover:shadow-md transition-all group">
                        <div class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 group-hover:bg-gray-800 group-hover:text-white transition-colors">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <span class="text-[15px] font-semibold text-gray-800">Search Properties</span>
                    </a>
                    <a href="{{ route('favorites.index') }}" class="flex items-center gap-4 p-4 rounded-[16px] border border-gray-200 bg-white hover:border-gray-800 hover:shadow-md transition-all group">
                        <div class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 group-hover:bg-gray-800 group-hover:text-white transition-colors">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        </div>
                        <span class="text-[15px] font-semibold text-gray-800">Saved Favorites</span>
                    </a>
                    <a href="{{ route('conversations.index') }}" class="flex items-center gap-4 p-4 rounded-[16px] border border-gray-200 bg-white hover:border-gray-800 hover:shadow-md transition-all group">
                        <div class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 group-hover:bg-gray-800 group-hover:text-white transition-colors">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                            </svg>
                        </div>
                        <span class="text-[15px] font-semibold text-gray-800">Open Messages</span>
                    </a>
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-4 p-4 rounded-[16px] border border-gray-200 bg-white hover:border-gray-800 hover:shadow-md transition-all group">
                        <div class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center text-gray-600 group-hover:bg-gray-800 group-hover:text-white transition-colors">
                            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <span class="text-[15px] font-semibold text-gray-800">Account Settings</span>
                    </a>
                </div>

                <div class="mb-4">
                    <h2 class="text-[20px] font-bold text-[#1A1A2E] tracking-tight">Recent Activity</h2>
                </div>
                <div class="bg-white border border-gray-200 rounded-[20px] p-6 shadow-sm">
                    @forelse($recentActivity ?? [] as $activity)
                        <div class="flex items-start gap-4 py-3.5 border-b border-gray-100 last:border-0 last:pb-0">
                            <div class="w-2 h-2 rounded-full bg-[#ff385c] flex-shrink-0 mt-2"></div>
                            <div class="flex-1 text-[14px] text-gray-700 leading-relaxed">
                                {!! preg_replace('/(\w+)/', '<strong class="text-gray-900">$1</strong>', $activity->message, 1) !!}
                            </div>
                            <div class="text-[12px] text-gray-400 font-medium whitespace-nowrap mt-0.5">{{ $activity->created_at->diffForHumans() }}</div>
                        </div>
                    @empty
                        <div class="flex items-start gap-4 py-3.5 border-b border-gray-100 last:border-0 last:pb-0">
                            <div class="w-2 h-2 rounded-full bg-[#ff385c] flex-shrink-0 mt-2"></div>
                            <div class="flex-1 text-[14px] text-gray-700 leading-relaxed">
                                Welcome to <strong class="text-gray-900">AbangananHub</strong>! Start by browsing verified properties.
                            </div>
                            <div class="text-[12px] text-gray-400 font-medium whitespace-nowrap mt-0.5">Just now</div>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- RIGHT: Become a Landlord card (hidden if already Landlord) --}}
            @if(!auth()->user()->hasRole('Landlord'))
                <div>
                    <div class="rounded-[24px] p-8 bg-gradient-to-br from-[#ff385c] to-[#e00b41] relative overflow-hidden shadow-[0_8px_30px_rgba(255,56,92,0.3)] text-white">
                        <div class="absolute -right-8 -top-8 w-36 h-36 rounded-full bg-white opacity-10"></div>
                        <div class="absolute -left-5 -bottom-8 w-[110px] h-[110px] rounded-full bg-white opacity-5"></div>
                        
                        <h3 class="text-[22px] font-extrabold mb-3 leading-[1.2] relative z-10">
                            Become a Host
                        </h3>
                        <p class="text-[14px] text-white/80 leading-[1.6] mb-6 relative z-10">
                            Earn extra income and unlock new opportunities by listing your space on AbangananHub.
                        </p>
                        
                        <a href="#" class="inline-flex items-center justify-center w-full py-3.5 bg-white text-[#e00b41] rounded-xl text-[15px] font-bold shadow-md hover:bg-gray-50 transition-all relative z-10">
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