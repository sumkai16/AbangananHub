@extends('layouts.admin')

@section('page-title', 'Property Verifications')

@section('content')
<div class="max-w-5xl">

    {{-- Page header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-extrabold text-[#1A1A2E] tracking-tight">Property Verifications</h1>
            <p class="text-[13.5px] text-gray-500 mt-1">Review pending property listings before they go live.</p>
        </div>
        <div class="bg-white border border-gray-100 rounded-2xl px-5 py-3 shadow-sm flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-[#2AA7A1]/10 flex items-center justify-center">
                <svg class="w-4.5 h-4.5 text-[#156F8C]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
            </div>
            <div>
                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Pending</p>
                <p class="text-[20px] font-extrabold text-[#1A1A2E] leading-none">
                    {{ isset($pendingListings) ? count($pendingListings) : 0 }}
                </p>
            </div>
        </div>
    </div>

    {{-- List --}}
    <div class="bg-white border border-gray-100 rounded-3xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-50">
            <h2 class="text-[15px] font-bold text-[#1A1A2E]">Pending Properties</h2>
            <p class="text-[13px] text-gray-400 mt-0.5">Approve to publish, or reject to request changes.</p>
        </div>

        <div class="divide-y divide-gray-50">
            @forelse($pendingListings ?? [] as $property)
                <div class="p-5 sm:p-6 flex flex-col sm:flex-row gap-5">

                    {{-- Thumbnail --}}
                    <div class="w-full sm:w-[140px] aspect-[4/3] rounded-2xl overflow-hidden bg-gray-50 border border-gray-100 shrink-0">
                        @php
                            $thumb = $property->thumbnail_url
                                ?? (method_exists($property, 'getFirstMediaUrl') ? $property->getFirstMediaUrl('images') : null);
                        @endphp
                        @if($thumb)
                            <img src="{{ $thumb }}" alt="{{ $property->title ?? 'Property' }}" class="w-full h-full object-cover" />
                        @else
                            <div class="w-full h-full flex items-center justify-center">
                                <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                </svg>
                            </div>
                        @endif
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 min-w-0 flex flex-col sm:flex-row sm:items-start gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap mb-3">
                                <h3 class="text-[16px] font-bold text-[#1A1A2E]">
                                    {{ $property->title ?? 'Untitled' }}
                                </h3>
                                <span class="text-[11px] font-bold uppercase tracking-wide px-2.5 py-0.5 rounded-full bg-[#2AA7A1]/10 text-[#156F8C] border border-[#2AA7A1]/20">
                                    {{ $property->type ?? 'Property' }}
                                </span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div class="flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                    </svg>
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Landlord</p>
                                        <p class="text-[13px] font-semibold text-[#1A1A2E]">
                                            {{ $property->landlord->name ?? trim(($property->user->first_name ?? '').' '.($property->user->last_name ?? '')) ?: '—' }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                    </svg>
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Rent</p>
                                        <p class="text-[13px] font-semibold text-[#1A1A2E]">
                                            {{ isset($property->price)
                                                ? '₱ '.number_format((float) $property->price, 2)
                                                : (isset($property->budget)
                                                    ? '₱ '.number_format((float) $property->budget, 2)
                                                    : ($property->price_formatted ?? '—')) }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 sm:col-span-2">
                                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                    </svg>
                                    <div>
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Location</p>
                                        <p class="text-[13px] font-semibold text-[#1A1A2E]">
                                            {{ $property->location ?? (($property->barangay ?? '') . ($property->city ?? '')) ?: '—' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex sm:flex-col gap-2 sm:items-end shrink-0">
                            <form method="POST" action="{{ route('admin.listings.approve', $property->id) }}">
                                @csrf
                                <button type="submit"
                                    class="w-full sm:w-[120px] h-10 inline-flex items-center justify-center gap-2 rounded-xl bg-[#22C55E] hover:brightness-95 text-white text-[13px] font-bold transition-colors shadow-sm">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Approve
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.listings.reject', $property->id) }}">
                                @csrf
                                <button type="submit"
                                    class="w-full sm:w-[120px] h-10 inline-flex items-center justify-center gap-2 rounded-xl bg-red-500 hover:bg-red-600 text-white text-[13px] font-bold transition-colors shadow-sm">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    Reject
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-16 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-gray-50 border border-gray-100 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="text-[15px] font-bold text-[#1A1A2E]">All caught up!</p>
                    <p class="text-[13px] text-gray-400 mt-1 max-w-xs mx-auto">
                        No pending property listings. Check back later for new submissions.
                    </p>
                </div>
            @endforelse
        </div>
    </div>

</div>
@endsection
