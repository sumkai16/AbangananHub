@extends('layouts.admin')

@section('page-title', 'Property Verifications')

@section('content')
<div class="max-w-7xl">

    {{-- Page header --}}
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#1F2937] tracking-tight">Property Verifications</h1>
            <p class="text-sm text-[#64748B] mt-1">Review pending property listings before they go live.</p>
        </div>
        @if ($counts['Pending'] > 0)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-[#FBBF24]/15 px-3 py-1.5 text-xs font-semibold text-[#92600A]">
                <svg class="h-3.5 w-3.5 text-[#FBBF24]" viewBox="0 0 24 24" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003zM12 8.25a.75.75 0 01.75.75v3.75a.75.75 0 01-1.5 0V9a.75.75 0 01.75-.75zm0 8.25a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd" />
                </svg>
                {{ $counts['Pending'] }} awaiting review
            </span>
        @endif
    </div>

    {{-- Stat summary --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        @php
            $stats = [
                'Pending' => ['label' => 'Pending', 'value' => $counts['Pending'], 'accent' => 'text-[#B45309]', 'dot' => 'bg-[#FBBF24]'],
                'Approved' => ['label' => 'Approved', 'value' => $counts['Approved'], 'accent' => 'text-[#15803D]', 'dot' => 'bg-[#22C55E]'],
                'Rejected' => ['label' => 'Rejected', 'value' => $counts['Rejected'], 'accent' => 'text-[#B91C1C]', 'dot' => 'bg-[#EF4444]'],
                'All' => ['label' => 'Total', 'value' => $counts['All'], 'accent' => 'text-[#156F8C]', 'dot' => 'bg-[#156F8C]'],
            ];
        @endphp
        @foreach ($stats as $key => $stat)
            <a href="{{ route('admin.listings.approval', ['status' => $key]) }}"
                class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl px-4 py-3.5 shadow-lg transition-all duration-200 hover:shadow-xl {{ $status === $key ? 'ring-2 ring-[#2AA7A1]' : '' }}">
                <div class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full {{ $stat['dot'] }}"></span>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-[#64748B]">{{ $stat['label'] }}</p>
                </div>
                <p class="text-2xl font-bold {{ $stat['accent'] }} mt-1">{{ $stat['value'] }}</p>
            </a>
        @endforeach
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl p-1 mb-5 w-fit max-w-full overflow-x-auto shadow-lg">
        @foreach (['Pending', 'Approved', 'Rejected', 'All'] as $tab)
            <a href="{{ route('admin.listings.approval', ['status' => $tab]) }}"
                class="px-4 py-1.5 rounded-xl text-[13px] font-semibold transition-all duration-200 whitespace-nowrap
                    {{ $status === $tab
                        ? 'bg-[#2AA7A1] text-white shadow-sm'
                        : 'text-[#64748B] hover:text-[#1F2937] hover:bg-[#F7FCFC]' }}">
                {{ $tab }}
                <span class="ml-1 text-[11px] {{ $status === $tab ? 'text-white/80' : 'text-[#94A3B8]' }}">{{ $counts[$tab] }}</span>
            </a>
        @endforeach
    </div>

    {{-- List --}}
    @if ($pendingListings->isEmpty())
        <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl p-16 text-center shadow-lg">
            <div class="w-14 h-14 rounded-2xl bg-[#EEF8F8] border border-white/30 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <p class="text-[15px] font-bold text-[#1F2937]">{{ $status === 'Pending' ? 'All caught up!' : 'No properties here' }}</p>
            <p class="text-[13px] text-[#64748B] mt-1 max-w-xs mx-auto">
                {{ $status === 'Pending' ? 'No pending property listings. Check back later for new submissions.' : 'No properties match this tab right now.' }}
            </p>
        </div>
    @else
        <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg overflow-hidden divide-y divide-[#E2E8F0]">
            @foreach($pendingListings as $property)
                @php
                    $thumb = $property->thumbnail_url
                        ?? (method_exists($property, 'getFirstMediaUrl') ? $property->getFirstMediaUrl('images') : null);
                @endphp
                <div class="p-5 sm:p-6 flex flex-col sm:flex-row gap-5 hover:bg-[#F7FCFC]/70 transition-all duration-200">

                    {{-- Thumbnail --}}
                    <div class="w-full sm:w-[140px] aspect-[4/3] rounded-2xl overflow-hidden bg-[#EEF8F8] border border-white/30 shrink-0">
                        @if($thumb)
                            <img src="{{ $thumb }}" alt="{{ $property->title ?? 'Property' }}" class="w-full h-full object-cover" />
                        @else
                            <div class="w-full h-full flex items-center justify-center">
                                <svg class="w-8 h-8 text-[#2AA7A1]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                </svg>
                            </div>
                        @endif
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0 flex flex-col sm:flex-row sm:items-start gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-3">
                                <h3 class="text-[16px] font-bold text-[#1F2937]">
                                    {{ $property->title ?? 'Untitled' }}
                                </h3>
                                <span class="text-[11px] font-bold uppercase tracking-wide px-2.5 py-0.5 rounded-full bg-[#2AA7A1]/10 text-[#156F8C] border border-[#2AA7A1]/20">
                                    {{ $property->type ?? 'Property' }}
                                </span>
                                <x-verification-status-badge :status="$property->verification_status" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div class="flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 text-[#94A3B8] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                    </svg>
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#94A3B8]">Landlord</p>
                                        <p class="text-[13px] font-semibold text-[#1F2937]">
                                            {{ $property->landlord->name ?? trim(($property->user->first_name ?? '').' '.($property->user->last_name ?? '')) ?: '—' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 text-[#94A3B8] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                    </svg>
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#94A3B8]">Rent</p>
                                        <p class="text-[13px] font-semibold text-[#1F2937]">
                                            {{ isset($property->price)
                                                ? '₱ '.number_format((float) $property->price, 2)
                                                : (isset($property->budget)
                                                    ? '₱ '.number_format((float) $property->budget, 2)
                                                    : ($property->price_formatted ?? '—')) }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 sm:col-span-2">
                                    <svg class="w-3.5 h-3.5 text-[#94A3B8] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                    </svg>
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-[#94A3B8]">Location</p>
                                        <p class="text-[13px] font-semibold text-[#1F2937]">
                                            {{ $property->location ?? (($property->barangay ?? '') . ($property->city ?? '')) ?: '—' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Actions --}}
                        @if ($property->verification_status === 'Pending')
                            <div class="flex sm:flex-col gap-2 sm:items-end shrink-0">
                                <form method="POST" action="{{ route('admin.listings.approve', $property->id) }}">
                                    @csrf
                                    <button type="submit"
                                        class="w-full sm:w-[120px] h-10 inline-flex items-center justify-center gap-2 rounded-xl bg-[#22C55E] hover:brightness-95 text-white text-[13px] font-bold transition-all duration-200 shadow-sm cursor-pointer">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Approve
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.listings.reject', $property->id) }}">
                                    @csrf
                                    <button type="submit"
                                        class="w-full sm:w-[120px] h-10 inline-flex items-center justify-center gap-2 rounded-xl bg-[#EF4444] hover:brightness-95 text-white text-[13px] font-bold transition-all duration-200 shadow-sm cursor-pointer">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Reject
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        @if ($pendingListings->hasPages())
            <div class="mt-4 bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl px-6 py-3 shadow-lg">
                {{ $pendingListings->links() }}
            </div>
        @endif
    @endif

</div>
@endsection
