{{-- resources/views/reservations/index.blade.php --}}
@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-[1140px] mx-auto px-4 sm:px-6 lg:px-8 py-12 relative">
        {{-- Subtle background ambient gradient blob for a modern UI vibe --}}
        <div class="absolute top-0 right-1/4 w-96 h-96 bg-gradient-to-tr from-blue-400/5 to-purple-400/5 rounded-full blur-3xl pointer-events-none"></div>

        {{-- Premium Modern Header with Gradient Accent --}}
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6 mb-12 pb-8 border-b border-slate-200/60 relative z-10">
            <div>
                <div class="flex items-center gap-2.5 text-xs font-black bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent uppercase tracking-widest mb-2">
                    <span class="h-2 w-2 rounded-full bg-blue-600 animate-pulse"></span>
                    Tenant Portal Dashboard
                </div>
                <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900 mb-2">My Reservations</h1>
                <p class="text-[15px] text-slate-500 font-medium">Review real-time processing status, scheduling details, and lease inquiries.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('properties.index') }}" class="inline-flex items-center justify-center px-5 py-3 text-sm font-bold text-slate-700 bg-white border border-slate-200/80 rounded-xl hover:bg-slate-50 hover:border-slate-300 transition-all duration-200 shadow-sm hover:shadow-md">
                    Browse Properties
                </a>
            </div>
        </div>

        {{-- Search & Filter Bar --}}
        <form method="GET" action="{{ route('reservations.index') }}"
            class="flex flex-col sm:flex-row gap-3 mb-10 p-4 bg-white rounded-2xl border border-slate-200/60 shadow-sm">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"
                    fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input type="text" name="search" value="{{ request('search') }}"
                    placeholder="Search property name or address…"
                    class="w-full pl-9 pr-4 py-2.5 text-sm text-slate-800 bg-[#F7F8FA] border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#286CD2]/30 focus:border-[#286CD2] transition placeholder-slate-400" />
            </div>
            <select name="status"
                class="px-4 py-2.5 text-sm font-semibold text-slate-700 bg-[#F7F8FA] border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-[#286CD2]/30 focus:border-[#286CD2] transition">
                <option value="All" {{ request('status', 'All') === 'All' ? 'selected' : '' }}>All statuses</option>
                @foreach(['Pending', 'Approved', 'Rejected', 'Cancelled'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
            <button type="submit"
                class="px-5 py-2.5 text-sm font-bold text-white bg-[#286CD2] hover:bg-[#1e57b0] rounded-xl shadow-sm transition-all duration-200">
                Search
            </button>
            @if(request()->hasAny(['search', 'status']))
                <a href="{{ route('reservations.index') }}"
                    class="px-4 py-2.5 text-sm font-semibold text-slate-500 bg-[#F7F8FA] border border-slate-200 hover:bg-slate-100 rounded-xl transition-all duration-200 text-center">
                    Clear
                </a>
            @endif
        </form>

        @if (session('success'))
            <div class="mb-8 p-4.5 rounded-2xl bg-gradient-to-r from-emerald-50 to-teal-50 text-emerald-900 text-sm font-semibold border border-emerald-100 shadow-[0_4px_12px_rgba(16,185,129,0.05)] flex items-center gap-3">
                <div class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"></path></svg>
                </div>
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-8 p-4.5 rounded-2xl bg-gradient-to-r from-rose-50 to-orange-50 text-rose-900 text-sm font-semibold border border-rose-100 shadow-[0_4px_12px_rgba(244,63,94,0.05)] flex items-center gap-3">
                <div class="p-1.5 rounded-lg bg-rose-500/10 text-rose-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path></svg>
                </div>
                {{ $errors->first() }}
            </div>
        @endif

        @if($reservations->isEmpty())
            <x-empty-state title="No active inquiries" message="Your submission queue is currently clear. Once you request a lease window, it will process directly here."
                href="{{ route('properties.index') }}" cta="Explore Available Listings" />
        @else
            <div class="space-y-8 relative z-10">
                @foreach($reservations as $reservation)
                    @continue(!$reservation->property)
                    @php
                        $statusStyles = match ($reservation->reservation_status) {
                            'Pending' => [
                                'badge' => 'bg-amber-500/10 text-amber-800 border-amber-200/50', 
                                'bg' => 'bg-amber-500',
                                'text' => 'Awaiting Verification'
                            ],
                            'Approved' => [
                                'badge' => 'bg-emerald-500/10 text-emerald-800 border-emerald-200/50', 
                                'bg' => 'bg-emerald-500',
                                'text' => 'Offer Approved'
                            ],
                            'Rejected' => [
                                'badge' => 'bg-rose-500/10 text-rose-800 border-rose-200/50', 
                                'bg' => 'bg-rose-500',
                                'text' => 'Declined'
                            ],
                            'Cancelled' => [
                                'badge' => 'bg-slate-500/10 text-slate-600 border-slate-200/50', 
                                'bg' => 'bg-slate-400',
                                'text' => 'Withdrawn'
                            ],
                            default => [
                                'badge' => 'bg-slate-500/10 text-slate-600 border-slate-200/50', 
                                'bg' => 'bg-slate-400',
                                'text' => 'Archived'
                            ],
                        };
                        $thumbnail = $reservation->property->media->firstWhere('media_type', 'Image');
                    @endphp

                    {{-- Next-Gen Asymmetric Dashboard Card Architecture with Rich Gradients & Shadows --}}
                    <div class="group bg-white rounded-3xl border border-slate-200/60 shadow-[0_8px_30px_rgba(30,41,59,0.02)] hover:shadow-[0_20px_50px_rgba(30,41,59,0.07)] hover:border-slate-300/80 transition-all duration-300 ease-out overflow-hidden">
                        
                        <div class="grid grid-cols-1 lg:grid-cols-12">
                            
                            {{-- Widescreen Left Image Section with Gradient Overlay --}}
                            <div class="lg:col-span-4 relative h-56 lg:h-full min-h-[240px] bg-slate-100 overflow-hidden">
                                @if($thumbnail)
                                    <img src="{{ $thumbnail->media_url }}" alt="{{ $reservation->property->title }}"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out">
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-slate-300 bg-gradient-to-br from-slate-50 to-slate-100">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="w-12 h-12">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                        </svg>
                                    </div>
                                @endif
                                {{-- Vignette gradient panel layered on top of image --}}
                                <div class="absolute inset-0 bg-gradient-to-t lg:bg-gradient-to-r from-slate-950/30 via-slate-950/5 to-transparent"></div>
                            </div>

                            {{-- Right Information Panel Workspace --}}
                            <div class="lg:col-span-8 p-6 sm:p-8 flex flex-col justify-between bg-gradient-to-br from-white via-white to-slate-50/40">
                                
                                <div>
                                    <div class="flex items-start justify-between gap-4 flex-wrap sm:flex-nowrap mb-5">
                                        <div class="min-w-0">
                                            <a href="{{ route('properties.show', $reservation->property) }}"
                                                class="text-xl font-extrabold text-slate-900 hover:text-blue-600 transition-colors tracking-tight truncate block mb-1">
                                                {{ $reservation->property->title }}
                                            </a>
                                            <div class="flex items-center gap-1.5 text-slate-400 font-medium text-sm">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="w-3.5 h-3.5 flex-shrink-0 text-slate-400">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                                </svg>
                                                <span class="truncate max-w-sm sm:max-w-md text-slate-500">{{ $reservation->property->address }}</span>
                                            </div>
                                        </div>

                                        {{-- Dynamic Floating Process Badge with Fine Micro-shadows --}}
                                        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl border text-[11px] font-black uppercase tracking-wider transition-all {{ $statusStyles['badge'] }} shadow-sm">
                                            <span class="h-2 w-2 rounded-full ring-4 ring-white {{ $statusStyles['bg'] }}"></span>
                                            {{ $statusStyles['text'] }}
                                        </span>
                                    </div>

                                    {{-- Geometric Frosted Metric Subgrid with Radial Backdrops --}}
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 rounded-2xl bg-gradient-to-br from-slate-50/90 to-slate-100/40 border border-slate-200/50 shadow-inner mb-6">
                                        <div class="space-y-1">
                                            <span class="text-[10px] font-bold text-slate-400 tracking-widest uppercase block">Move-In Target</span>
                                            <p class="text-[13.5px] text-slate-800 font-bold flex items-center gap-1.5">
                                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                                {{ $reservation->reservation_date->format('M d, Y') }}
                                            </p>
                                        </div>
                                        <div class="space-y-1 border-l border-slate-200/80 pl-4">
                                            <span class="text-[10px] font-bold text-slate-400 tracking-widest uppercase block">Lease Span</span>
                                            <p class="text-[13.5px] text-blue-700 font-bold flex items-center gap-1.5">
                                                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                {{ $reservation->duration_of_stay }}
                                            </p>
                                        </div>
                                        <div class="space-y-1 border-l border-slate-200/80 pl-4 col-span-1">
                                            <span class="text-[10px] font-bold text-slate-400 tracking-widest uppercase block">Total Group</span>
                                            <p class="text-[13.5px] text-purple-700 font-bold flex items-center gap-1.5">
                                                <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                                {{ $reservation->occupants_count }} {{ Str::plural('Person', $reservation->occupants_count) }}
                                            </p>
                                        </div>
                                        <div class="space-y-1 border-l border-slate-200/80 pl-4 col-span-1">
                                            <span class="text-[10px] font-bold text-slate-400 tracking-widest uppercase block">Host/Landlord</span>
                                            <p class="text-[13.5px] text-slate-800 font-bold truncate">
                                                {{ trim($reservation->property->landlord->first_name . ' ' . $reservation->property->landlord->last_name) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Footnote / Utility Actions Footer --}}
                                <div class="pt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-t border-slate-100/80">
                                    <div class="min-w-0 flex items-center gap-2">
                                        @if($reservation->remarks)
                                            <div class="p-1 rounded bg-slate-100 text-slate-400 flex-shrink-0">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path></svg>
                                            </div>
                                            <p class="text-[13.5px] font-medium text-slate-500 italic truncate max-w-md md:max-w-xl">
                                                "{{ $reservation->remarks }}"
                                            </p>
                                        @else
                                            <p class="text-[13px] text-slate-400 font-medium italic">No custom messaging parameters attached.</p>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-3 sm:ml-auto flex-shrink-0">
                                        {{-- Secondary Action Contact Info with Soft Shadow Profile --}}
                                        <span class="text-xs font-semibold text-slate-500 border border-slate-200/80 rounded-xl px-3 py-2 bg-white flex items-center gap-1.5 shadow-[0_2px_6px_rgba(0,0,0,0.02)]">
                                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                                            {{ $reservation->property->landlord->contact_number ?? 'No Line' }}
                                        </span>

                                        @if($reservation->isPending() || $reservation->isApproved())
                                            <form action="{{ route('reservations.cancel', $reservation) }}" method="POST"
                                                onsubmit="return confirm('Withdraw this reservation proposal?');" class="inline-block">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                    class="px-4 py-2 text-xs font-bold text-slate-500 bg-white border border-slate-200 hover:bg-gradient-to-r hover:from-rose-50 hover:to-red-50 hover:text-rose-600 hover:border-rose-200/80 rounded-xl shadow-sm transition-all duration-200">
                                                    Withdraw
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>
                @endforeach
            </div>

            {{-- Custom Paginated Control Segment --}}
            <div class="mt-12 pt-6 border-t border-slate-200/60">
                {{ $reservations->links() }}
            </div>
        @endif

    </div>
@endsection