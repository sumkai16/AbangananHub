<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 pb-2">
            <div>
                <h2 class="font-extrabold text-3xl text-slate-900 tracking-tight">Tenant Ecosystem</h2>
                <p class="text-sm font-medium text-slate-500 mt-1">Monitor your reservations, active leases, and secure gateways.</p>
            </div>
            <div class="flex items-center gap-3 bg-white border border-slate-200/80 rounded-2xl p-2.5 shadow-sm hover:shadow-md transition-shadow duration-300 self-start sm:self-center">
                <div class="relative flex h-3 w-3 ml-1.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                </div>
                <div class="text-sm text-slate-600 font-medium pr-2">
                    Welcome back, <span class="font-bold text-slate-900">{{ trim((Auth::user()?->first_name ?? '') . ' ' . (Auth::user()?->last_name ?? '')) ?: (Auth::user()?->email ?? 'Tenant') }}</span>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-10 bg-gradient-to-b from-slate-50 to-slate-100/80 min-h-screen">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-12 gap-8">
                
                <aside id="sidebar" class="col-span-12 lg:col-span-3 transition-all duration-300">
                    <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-200/60 sticky top-6 space-y-6 backdrop-blur-md">
                        
                        <div class="flex items-center gap-4 pb-5 border-b border-slate-100">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 via-[#286CD2] to-[#1e4fa3] flex items-center justify-center text-white font-extrabold text-lg shadow-md shadow-blue-500/20 transform transition hover:scale-105 duration-300">
                                {{ strtoupper(substr(Auth::user()?->first_name ?? 'T',0,1)) }}
                            </div>
                            <div class="min-w-0">
                                <div class="text-slate-900 font-bold text-base tracking-tight truncate">{{ trim((Auth::user()?->first_name ?? '') . ' ' . (Auth::user()?->last_name ?? '')) ?: (Auth::user()?->email ?? 'Tenant') }}</div>
                                <div class="text-xs text-slate-400 font-medium mt-0.5 truncate">{{ Auth::user()?->email }}</div>
                            </div>
                        </div>

                        <nav class="space-y-2" role="navigation" aria-label="Tenant sidebar navigation">
                            @php
                                $current = request()->path();
                                $is = function($pattern) use ($current) { return Str::startsWith($current, ltrim($pattern, '/')); };
                            @endphp

                            <a href="{{ route('dashboard') }}" class="relative group flex items-center gap-3.5 px-4 py-3 rounded-2xl text-sm font-semibold transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 {{ request()->routeIs('dashboard') ? 'bg-gradient-to-r from-[#1e4fa3] to-[#286CD2] text-white shadow-md' : 'text-slate-700 hover:bg-slate-50' }}" aria-current="{{ request()->routeIs('dashboard') ? 'page' : 'false' }}">
                                <span class="absolute left-0 h-full w-1 rounded-l-2xl bg-transparent group-hover:bg-[#286CD2] {{ request()->routeIs('dashboard') ? 'bg-white/0' : '' }}" aria-hidden="true"></span>
                                <svg class="w-5 h-5 flex-shrink-0 {{ request()->routeIs('dashboard') ? 'text-white' : 'text-blue-500' }}" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h4v13H3zM9 3h4v17H9zM15 10h4v10h-4z"/></svg>
                                <span class="truncate">Overview</span>
                                @if(request()->routeIs('dashboard'))
                                    <span class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-white/10">Active</span>
                                @endif
                            </a>

                            <a href="{{ route('profile.edit') }}" class="group flex items-center gap-3.5 px-4 py-3 rounded-2xl text-sm font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 {{ request()->routeIs('profile.*') ? 'bg-slate-50 text-slate-900 shadow-sm' : 'text-slate-600 hover:bg-slate-50' }}" aria-current="{{ request()->routeIs('profile.*') ? 'page' : 'false' }}">
                                <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4a4 4 0 110 8 4 4 0 010-8zM6 20a6 6 0 0112 0"/></svg>
                                <span class="truncate">Account Settings</span>
                            </a>

                            <a href="#reservations" class="group flex items-center gap-3.5 px-4 py-3 rounded-2xl text-sm font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 text-slate-600 hover:bg-slate-50">
                                <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span class="truncate">My Reservations</span>
                            </a>

                            <a href="#favorites" class="group flex items-center gap-3.5 px-4 py-3 rounded-2xl text-sm font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 text-slate-600 hover:bg-slate-50">
                                <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                <span class="truncate">Saved Favorites</span>
                            </a>

                            <a href="#messages" class="group flex items-center gap-3.5 px-4 py-3 rounded-2xl text-sm font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 text-slate-600 hover:bg-slate-50">
                                <svg class="w-5 h-5 text-slate-400 group-hover:text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                                <span class="truncate">Messages</span>
                            </a>
                        </nav>

                        <div class="pt-2">
                            <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                               class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-2xl bg-rose-50 hover:bg-rose-100 border border-rose-100/80 text-sm font-bold text-rose-600 transition-all duration-200 shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Sign out
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
                        </div>
                    </div>
                </aside>

                <section class="col-span-12 lg:col-span-9 space-y-8">
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
                        
                        <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-200/60 flex items-center justify-between group hover:shadow-md transition-all duration-300">
                            <div class="space-y-1">
                                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Upcoming Stays</div>
                                <div class="text-3xl font-black text-slate-800 tracking-tight group-hover:text-blue-600 transition-colors">{{ $upcomingCount ?? 0 }}</div>
                            </div>
                            <div class="bg-blue-50/80 border border-blue-100 w-12 h-12 rounded-2xl flex items-center justify-center text-[#286CD2] group-hover:scale-110 transition-transform duration-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        </div>

                        <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-200/60 flex items-center justify-between group hover:shadow-md transition-all duration-300">
                            <div class="space-y-1">
                                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Messages</div>
                                <div class="text-3xl font-black text-slate-800 tracking-tight group-hover:text-indigo-600 transition-colors">{{ $messagesCount ?? 0 }}</div>
                            </div>
                            <div class="bg-indigo-50/80 border border-indigo-100 w-12 h-12 rounded-2xl flex items-center justify-center text-indigo-600 group-hover:scale-110 transition-transform duration-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                            </div>
                        </div>

                        <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-200/60 flex items-center justify-between group hover:shadow-md transition-all duration-300">
                            <div class="space-y-1">
                                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Saved Listings</div>
                                <div class="text-3xl font-black text-slate-800 tracking-tight group-hover:text-emerald-600 transition-colors">{{ $savedCount ?? 0 }}</div>
                            </div>
                            <div class="bg-emerald-50/80 border border-emerald-100 w-12 h-12 rounded-2xl flex items-center justify-center text-emerald-600 group-hover:scale-110 transition-transform duration-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                            </div>
                        </div>

                        <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-200/60 flex items-center justify-between group hover:shadow-md transition-all duration-300">
                            <div class="space-y-1">
                                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Support Tickets</div>
                                <div class="text-3xl font-black text-slate-800 tracking-tight group-hover:text-amber-600 transition-colors">{{ $supportCount ?? 0 }}</div>
                            </div>
                            <div class="bg-amber-50/80 border border-amber-100 w-12 h-12 rounded-2xl flex items-center justify-center text-amber-600 group-hover:scale-110 transition-transform duration-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-1.414 1.414M6.05 17.95l-1.414 1.414M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        
                        <div class="lg:col-span-2 bg-white rounded-3xl p-6 shadow-sm border border-slate-200/60 flex flex-col justify-between">
                            <div>
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                                    <div>
                                        <h3 class="font-extrabold text-lg text-slate-900 tracking-tight">Upcoming Reservations</h3>
                                        <p class="text-xs font-medium text-slate-400 mt-0.5">Real-time tracking of active or upcoming properties.</p>
                                    </div>
                                    
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        <div class="relative" role="search" aria-label="Search reservations">
                                            <label for="reservation-search" class="sr-only">Search reservations by property or reference</label>
                                            <input type="text" id="reservation-search" placeholder="Search property or ref..." aria-describedby="reservation-search-desc"
                                                   class="text-xs bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 focus:bg-white focus:ring-4 focus:ring-blue-500/10 focus:border-[#286CD2] transition-all w-56 sm:w-48 font-medium placeholder-slate-400 text-slate-700" />
                                            <div id="reservation-search-desc" class="sr-only">Type to filter reservations</div>
                                            <div class="absolute left-3 top-2.5 text-slate-400">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                            </div>
                                        </div>
                                        <select id="reservation-filter" aria-label="Filter reservations by status" class="text-xs font-semibold border border-slate-200 bg-slate-50 rounded-xl px-3 py-2 text-slate-600 focus:bg-white focus:ring-4 focus:ring-blue-500/10 focus:border-[#286CD2] transition-all cursor-pointer">
                                            <option value="all">All Statuses</option>
                                            <option value="Approved">Approved</option>
                                            <option value="Pending">Pending</option>
                                            <option value="Cancelled">Cancelled</option>
                                        </select>
                                    </div>
                                </div>

                                <div id="reservations-list" class="space-y-3.5">
                                    @forelse($reservations as $reservation)
                                        <div class="reservation-row flex flex-col sm:flex-row sm:items-center justify-between p-4 rounded-2xl bg-slate-50/40 hover:bg-slate-50/90 border border-slate-200/40 hover:border-slate-200/80 transition-all duration-200 gap-4 group"
                                             data-status="{{ $reservation->reservation_status }}">
                                            <div class="flex items-center gap-4">
                                                <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-[#286CD2] rounded-xl flex items-center justify-center text-white font-extrabold text-sm shadow-inner group-hover:scale-105 transition-transform shrink-0">
                                                    {{ strtoupper(substr($reservation->property->title ?? 'P',0,1)) }}
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="font-bold text-sm text-slate-800 tracking-tight truncate property-title group-hover:text-slate-900 transition-colors">{{ $reservation->property->title ?? 'Property Title missing' }}</div>
                                                    <div class="text-xs text-slate-400 font-medium mt-1">
                                                        Check-in: <span class="text-slate-600 font-semibold">{{ $reservation->reservation_date?->format('M d, Y') ?? 'N/A' }}</span> • <span class="text-slate-600 font-semibold">{{ $reservation->nights ?? '—' }} nights</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex sm:flex-col items-center sm:items-end justify-between sm:justify-center border-t sm:border-t-0 pt-3 sm:pt-0 border-slate-100">
                                                @php
                                                    $statusColor = match($reservation->reservation_status) {
                                                        'Approved' => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                                        'Pending' => 'bg-amber-50 text-amber-700 border-amber-100',
                                                        default => 'bg-slate-100 text-slate-600 border-slate-200'
                                                    };
                                                @endphp
                                                <span class="px-3 py-1 rounded-full text-[10px] font-bold tracking-wider uppercase border {{ $statusColor }}">
                                                    {{ $reservation->reservation_status }}
                                                </span>
                                                <div class="text-[10px] text-slate-400 font-bold mt-1.5 tracking-mono uppercase reference-id">Ref: #R{{ $reservation->id }}</div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-center text-slate-400 py-12 px-6 border-2 border-dashed border-slate-200 rounded-2xl space-y-3 bg-gradient-to-b from-white/50 to-slate-50">
                                            <div class="mx-auto w-28 h-28 bg-gradient-to-br from-blue-50 to-[#e6f0ff] rounded-2xl flex items-center justify-center">
                                                <svg class="w-12 h-12 text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 11v6" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 11h8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </div>
                                            <p class="text-sm font-semibold text-slate-600">No upcoming reservations yet.</p>
                                            <p class="text-xs text-slate-400">Use the <strong>Search Properties</strong> button to find and book stays, or contact support for help.</p>
                                            <div class="mt-4">
                                                <a href="#" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-gradient-to-r from-[#286CD2] to-blue-500 text-white font-bold text-sm shadow-md">Search properties
                                                </a>
                                            </div>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <aside class="bg-white rounded-3xl p-6 shadow-sm border border-slate-200/60 space-y-5">
                            <div>
                                <h4 class="font-extrabold text-xs text-slate-900 uppercase tracking-wider">Quick Actions</h4>
                                <p class="text-xs font-medium text-slate-400 mt-0.5">Quick access shortcuts to critical workflows.</p>
                            </div>
                            
                            <div class="space-y-3">
                                <a href="#" class="flex items-center justify-between w-full px-4 py-3.5 rounded-2xl bg-gradient-to-r from-[#286CD2] to-blue-500 hover:opacity-95 text-white font-bold text-sm shadow-md shadow-blue-500/20 transition-all duration-200 group transform hover:-translate-y-0.5">
                                    Search Properties
                                    <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </a>
                                <a href="#" class="flex items-center gap-3 w-full px-4 py-3.5 rounded-2xl border border-slate-200/80 hover:bg-slate-50 text-slate-700 font-bold text-sm transition-all duration-200 group">
                                    <svg class="w-4 h-4 text-slate-400 group-hover:text-rose-500 transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                    View Favorites
                                </a>
                                <a href="#" class="flex items-center gap-3 w-full px-4 py-3.5 rounded-2xl border border-slate-200/80 hover:bg-slate-50 text-slate-700 font-bold text-sm transition-all duration-200 group">
                                    <svg class="w-4 h-4 text-slate-400 group-hover:text-blue-500 transition-colors" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636l-3.536 3.536m0 0A7.5 7.5 0 1015.75 21a7.5 7.5 0 00-1.25-10.25M14.828 8.485L12 11.314M9.172 14.142L6.343 16.97"/></svg>
                                    Contact Support
                                </a>
                            </div>
                        </aside>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>

<script>
    (function(){
        // Smoothly managed hidden sidebar responsive anchor injection
        const btn = document.createElement('button');
        btn.innerHTML = '<span class="sr-only">Toggle sidebar</span><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>';
        btn.className = 'lg:hidden p-2.5 rounded-xl bg-white border border-slate-200 text-slate-700 shadow-sm transition-all hover:bg-slate-50 active:scale-95 shrink-0 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500';
        btn.setAttribute('aria-controls','sidebar');
        btn.setAttribute('aria-expanded','false');
        
        const header = document.querySelector('[slot="header"]') || document.querySelector('header') || document.querySelector('.flex.items-center.justify-between');
        if (header) {
            header.prepend(btn);
        }

        btn.addEventListener('click', () => {
            const sidebar = document.getElementById('sidebar');
            if (sidebar) {
                const expanded = btn.getAttribute('aria-expanded') === 'true';
                btn.setAttribute('aria-expanded', String(!expanded));
                sidebar.classList.toggle('hidden');
                // animate on small screens
                sidebar.classList.toggle('translate-x-0');
                sidebar.classList.toggle('-translate-x-full');
                window.dispatchEvent(new Event('resize'));
            }
        });

        // Combined Filter and Omni Search Controller
        const filterDropdown = document.getElementById('reservation-filter');
        const searchInput = document.getElementById('reservation-search');
        const rows = document.querySelectorAll('#reservations-list .reservation-row');

        function processUnifiedFilters() {
            const statusValue = filterDropdown ? filterDropdown.value : 'all';
            const searchValue = searchInput ? searchInput.value.toLowerCase().trim() : '';

            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                const titleText = row.querySelector('.property-title')?.textContent.toLowerCase() || '';
                const refText = row.querySelector('.reference-id')?.textContent.toLowerCase() || '';

                const matchesStatus = (statusValue === 'all' || rowStatus === statusValue);
                const matchesSearch = (searchValue === '' || titleText.includes(searchValue) || refText.includes(searchValue));

                if (matchesStatus && matchesSearch) {
                    row.style.display = '';
                    row.style.opacity = '1';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        if (filterDropdown) filterDropdown.addEventListener('change', processUnifiedFilters);
        if (searchInput) searchInput.addEventListener('input', processUnifiedFilters);

        // initial filter pass
        setTimeout(processUnifiedFilters, 60);
    })();
</script>