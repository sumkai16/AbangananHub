@extends('layouts.app')

@section('hide_search', true)

@section('content')
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/70 border border-gray-200 shadow-sm">
                        <span class="w-2.5 h-2.5 rounded-full bg-[#286CD2]"></span>
                        <span class="text-[12px] sm:text-[13px] font-bold text-[#1A1A2E] uppercase tracking-wide">
                            Admin Dashboard
                        </span>
                    </div>
                    <h1 class="mt-3 text-2xl sm:text-3xl font-black text-[#1A1A2E] tracking-tight">
                        Listing Approval
                    </h1>
                    <p class="mt-2 text-sm sm:text-[14px] text-gray-500">
                        Review pending property listings before they go live.
                    </p>
                </div>

                <div class="rounded-2xl bg-white/80 border border-gray-200 shadow-sm p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl bg-[#286CD2]/10 flex items-center justify-center">
                            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#286CD2" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-[12px] font-bold text-gray-500 uppercase tracking-wide">Pending</div>
                            <div class="text-[18px] font-extrabold text-[#1A1A2E]">
                                {{ isset($pendingListings) ? count($pendingListings) : 0 }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Body Card --}}
        <div class="bg-white/70 backdrop-blur border border-gray-200 rounded-[24px] shadow-[0_10px_40px_rgba(17,24,39,0.06)]">
            <div class="p-5 sm:p-7 border-b border-gray-100">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-[16px] sm:text-[18px] font-extrabold text-[#1A1A2E]">Pending Properties</h2>
                        <p class="text-sm text-gray-500 mt-1">Approve to publish, or reject to request changes.</p>
                    </div>

                    {{-- Optional: small legend --}}
                    <div class="hidden md:flex items-center gap-4 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#16a34a]"></span>
                            <span class="text-gray-600 font-semibold">Approve</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-[#dc2626]"></span>
                            <span class="text-gray-600 font-semibold">Reject</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 sm:p-6">
                @forelse($pendingListings ?? [] as $property)
                    <div class="grid grid-cols-1 gap-4 sm:gap-0 sm:grid-cols-[160px_1fr] border border-gray-200 rounded-2xl p-4 sm:p-5 mb-4 last:mb-0 bg-gradient-to-br from-white to-gray-50/40">
                        {{-- Thumbnail --}}
                        <div class="flex sm:items-start">
                            <div class="w-full sm:w-[150px] aspect-[4/3] rounded-2xl overflow-hidden bg-gray-100 border border-gray-200 shadow-sm">
                                @php
                                    $thumb = $property->thumbnail_url
                                        ?? (method_exists($property, 'getFirstMediaUrl') ? $property->getFirstMediaUrl('images') : null);
                                @endphp
                                @if($thumb)
                                    <img src="{{ $thumb }}" alt="{{ $property->title ?? 'Property' }}" class="w-full h-full object-cover" />
                                @else
                                    <div class="w-full h-full flex items-center justify-center">
                                        <svg width="34" height="34" fill="none" viewBox="0 0 24 24" stroke="#9CA3AF" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4-4 4 4 4-8 4 8H4z" />
                                        </svg>
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Content --}}
                        <div class="flex flex-col">
                            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="text-[16px] sm:text-[17px] font-extrabold text-[#1A1A2E]">
                                            {{ $property->title ?? 'Untitled' }}
                                        </h3>
                                        <span class="text-[12px] font-bold uppercase tracking-wide px-3 py-1 rounded-full bg-[#286CD2]/10 text-[#286CD2] border border-[#286CD2]/20">
                                            {{ $property->type ?? 'Property' }}
                                        </span>
                                    </div>

                                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        <div class="flex items-start gap-2">
                                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#6B7280" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.5 11a4 4 0 100-8 4 4 0 000 8z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 8v6" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M23 11h-6" />
                                            </svg>
                                            <div>
                                                <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Landlord</div>
                                                <div class="text-sm font-semibold text-gray-800">
                                                    {{ $property->landlord->name ?? trim(($property->user->first_name ?? '').' '.($property->user->last_name ?? '')) ?: '—' }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-start gap-2">
                                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#6B7280" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 1v22" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 5H9.5a3.5 3.5 0 000 7H14a3.5 3.5 0 010 7H6" />
                                            </svg>
                                            <div>
                                                <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Price / Budget</div>
                                                <div class="text-sm font-semibold text-gray-800">
                                                    {{ isset($property->price)
                                                        ? '₱ '.number_format((float) $property->price, 2)
                                                        : (isset($property->budget)
                                                            ? '₱ '.number_format((float) $property->budget, 2)
                                                            : ($property->price_formatted ?? '—')) }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex items-start gap-2 sm:col-span-2">
                                            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#6B7280" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-4.35-7-10a7 7 0 1114 0c0 5.65-7 10-7 10z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 11a2 2 0 100-4 2 2 0 000 4z" />
                                            </svg>
                                            <div>
                                                <div class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Location</div>
                                                <div class="text-sm font-semibold text-gray-800">
                                                    {{ $property->location ?? ($property->barangay ?? '').($property->city ?? '') ?: '—' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:justify-end">
                                    {{-- Approve --}}
                                    <form method="POST"
                                        action="{{ route('admin.listings.approve', $property->id) }}"
                                        class="w-full sm:w-auto">
                                        @csrf
                                        @method('POST')
                                        <button type="submit"
                                            class="w-full sm:w-[140px] inline-flex items-center justify-center gap-2 h-11 rounded-xl bg-[#16a34a] hover:bg-[#15803d] text-white font-extrabold shadow-sm transition-all active:scale-[0.99]">
                                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                            Approve
                                        </button>
                                    </form>

                                    {{-- Reject --}}
                                    <form method="POST"
                                        action="{{ route('admin.listings.reject', $property->id) }}"
                                        class="w-full sm:w-auto">
                                        @csrf
                                        @method('POST')
                                        <button type="submit"
                                            class="w-full sm:w-[140px] inline-flex items-center justify-center gap-2 h-11 rounded-xl bg-[#dc2626] hover:bg-[#dc2626]/90 text-white font-extrabold shadow-sm transition-all active:scale-[0.99]">
                                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    {{-- Empty state --}}
                    <div class="py-14">
                        <div class="flex flex-col items-center text-center">
                            <div class="w-16 h-16 rounded-3xl bg-[#286CD2]/10 border border-[#286CD2]/20 flex items-center justify-center shadow-sm">
                                <svg width="34" height="34" fill="none" viewBox="0 0 24 24" stroke="#286CD2" stroke-width="2.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h6" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 2l8 4v6c0 5-3.5 9.5-8 10-4.5-.5-8-5-8-10V6l8-4z" />
                                </svg>
                            </div>
                            <h3 class="mt-5 text-[18px] font-extrabold text-[#1A1A2E]">No pending approvals found</h3>
                            <p class="mt-2 text-sm text-gray-500 max-w-[420px]">
                                All property listings are up to date. Check back later for new submissions.
                            </p>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endsection

