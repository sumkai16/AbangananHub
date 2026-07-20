@extends('layouts.landlord')

@section('content')
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-16">

        {{-- Breadcrumb --}}
        <div class="flex flex-wrap items-center gap-1.5 text-sm text-[#64748B] mb-2">
            <a href="{{ route('landlord.properties.index') }}"
                class="hover:text-[#1F2937] transition-colors duration-200">Properties</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <a href="{{ route('landlord.properties.show', $property) }}"
                class="hover:text-[#1F2937] transition-colors duration-200">{{ $property->title }}</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <a href="{{ route('landlord.properties.units.index', $property) }}"
                class="hover:text-[#1F2937] transition-colors duration-200">Units</a>
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-[#1F2937] font-medium">Add New Unit</span>
        </div>

        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-[#1F2937] leading-tight">Add New Unit</h1>
            <p class="text-sm text-[#64748B] mt-1">Add a new rental unit under your property.</p>
        </div>

        {{-- Flash / errors --}}
        @if($errors->any())
            <div class="mb-6 px-4 py-3 rounded-xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 text-[#DC2626] text-sm font-medium flex items-start gap-2.5">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="shrink-0 mt-0.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <div class="space-y-0.5">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            </div>
        @endif

        @php
            $statusMeta = [
                'Available' => ['dot' => '#22C55E', 'text' => 'Vacant and ready'],
                'Reserved' => ['dot' => '#FBBF24', 'text' => 'On hold for a tenant'],
                'Occupied' => ['dot' => '#EF4444', 'text' => 'Currently rented'],
                'Maintenance' => ['dot' => '#64748B', 'text' => 'Temporarily unavailable'],
            ];
            $amenityNameMap = $amenities->pluck('amenity_name', 'amenity_id')->toArray();
            $preselectedAmenities = collect(old('amenities', []))->map(fn ($id) => (string) $id)->all();
        @endphp

        <form method="POST" action="{{ route('landlord.properties.units.store', $property) }}" enctype="multipart/form-data"
            x-data="{
                unitLabel: @js(old('unit_label', '')),
                unitType: @js(old('unit_type', '')),
                floor: @js(old('floor', '')),
                rentalFee: @js(old('rental_fee', '')),
                securityDeposit: @js(old('security_deposit', '')),
                capacity: @js(old('occupancy_limit', '')),
                status: @js(old('availability_status', 'Available')),
                description: @js(old('description', '')),
                amenities: @js($preselectedAmenities),
                amenityNames: @js($amenityNameMap),
                statusMeta: @js($statusMeta),
                peso(v) { return (v === '' || v === null || isNaN(v)) ? null : '₱' + Number(v).toLocaleString('en-PH', { maximumFractionDigits: 2 }); },
            }">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

                {{-- ── Left column: form fields ──────────────────────────── --}}
                <div class="lg:col-span-7 space-y-6">

                    {{-- Property Information (read-only) --}}
                    <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-6">
                        <div class="flex items-center gap-2.5 mb-5">
                            <div class="w-8 h-8 rounded-lg bg-[#156F8C] flex items-center justify-center shrink-0">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                                </svg>
                            </div>
                            <h2 class="text-[13px] font-bold text-[#1F2937]">Property Information</h2>
                        </div>

                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label for="rental-business" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Rental Business</label>
                                <input type="text" id="rental-business" value="{{ $property->rentalBusiness->business_name ?? 'N/A' }}" disabled
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 bg-[#EEF8F8] px-3.5 text-[13.5px] text-[#64748B] cursor-not-allowed">
                            </div>
                            <div>
                                <label for="property-display" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Property</label>
                                <input type="text" id="property-display" value="{{ $property->title . ' - ' . $property->address }}" disabled
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 bg-[#EEF8F8] px-3.5 text-[13.5px] text-[#64748B] cursor-not-allowed">
                            </div>
                        </div>
                    </div>

                    {{-- Unit Details --}}
                    <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-6">
                        <div class="flex items-center gap-2.5 mb-5">
                            <div class="w-8 h-8 rounded-lg bg-[#1F2937] flex items-center justify-center shrink-0">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25zm9.75-9.75A2.25 2.25 0 0 1 15.75 3.75H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25z" />
                                </svg>
                            </div>
                            <h2 class="text-[13px] font-bold text-[#1F2937]">Unit Details</h2>
                        </div>

                        {{-- Row 1 --}}
                        <div class="grid sm:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label for="unit_label" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                                    Unit Name / Number <span class="text-[#EF4444]">*</span>
                                </label>
                                <input type="text" id="unit_label" name="unit_label" x-model="unitLabel" required maxlength="100"
                                    placeholder="e.g. Room 101, Bed A"
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                                @error('unit_label')
                                    <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Unit Type</label>
                                <select name="unit_type" x-model="unitType"
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3 text-[13.5px] text-[#1F2937] bg-white focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                                    <option value="">Select type</option>
                                    @foreach(['Bedspace', 'Room', 'Apartment', 'Studio', 'Dormitory'] as $type)
                                        <option value="{{ $type }}">{{ $type }}</option>
                                    @endforeach
                                </select>
                                @error('unit_type')
                                    <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="floor" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Floor</label>
                                <input type="text" id="floor" name="floor" x-model="floor" maxlength="50"
                                    placeholder="e.g. 1st Floor"
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                                @error('floor')
                                    <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Row 2 --}}
                        <div class="grid sm:grid-cols-3 gap-4 mb-5">
                            <div>
                                <label for="rental_fee" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                                    Monthly Rent (₱) <span class="text-[#EF4444]">*</span>
                                </label>
                                <input type="number" id="rental_fee" name="rental_fee" x-model="rentalFee" required min="500"
                                    max="999999.99" step="0.01" placeholder="e.g. 3500"
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                                @error('rental_fee')
                                    <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="security_deposit" class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Security Deposit (₱)</label>
                                <input type="number" id="security_deposit" name="security_deposit" x-model="securityDeposit" min="0"
                                    max="999999.99" step="0.01" placeholder="e.g. 3500"
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                                @error('security_deposit')
                                    <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                                    Capacity <span class="text-[#EF4444]">*</span>
                                </label>
                                <select name="occupancy_limit" x-model="capacity" required
                                    class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3 text-[13.5px] text-[#1F2937] bg-white focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                                    <option value="">Select capacity</option>
                                    @for($i = 1; $i <= 20; $i++)
                                        <option value="{{ $i }}">{{ $i }} {{ $i === 1 ? 'person' : 'persons' }}</option>
                                    @endfor
                                </select>
                                @error('occupancy_limit')
                                    <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Status --}}
                        <div class="mb-5">
                            <label class="block text-[12px] font-semibold text-[#1F2937] mb-2">
                                Status <span class="text-[#EF4444]">*</span>
                            </label>
                            @php
                                $statusOptions = [
                                    'Available' => ['desc' => 'Unit is vacant and ready', 'dot' => 'bg-[#22C55E]', 'active' => 'border-[#22C55E]/35 bg-[#22C55E]/[0.07]'],
                                    'Reserved' => ['desc' => 'On hold for a tenant', 'dot' => 'bg-[#FBBF24]', 'active' => 'border-[#FBBF24]/45 bg-[#FBBF24]/[0.10]'],
                                    'Occupied' => ['desc' => 'Currently rented', 'dot' => 'bg-[#EF4444]', 'active' => 'border-[#EF4444]/35 bg-[#EF4444]/[0.07]'],
                                    'Maintenance' => ['desc' => 'Temporarily unavailable', 'dot' => 'bg-[#64748B]', 'active' => 'border-[#E2E8F0] bg-[#F7FCFC]'],
                                ];
                                $inactiveClass = 'border-[#64748B]/25 bg-white hover:border-[#64748B]/40';
                            @endphp
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                @foreach($statusOptions as $value => $opt)
                                    <label class="relative cursor-pointer rounded-xl border px-3 py-3 transition-colors duration-150"
                                        :class="status === '{{ $value }}' ? '{{ $opt['active'] }}' : '{{ $inactiveClass }}'">
                                        <input type="radio" name="availability_status" value="{{ $value }}" x-model="status"
                                            class="sr-only">
                                        <div class="flex items-center gap-1.5 mb-0.5">
                                            <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $opt['dot'] }}"></span>
                                            <p class="text-[13px] font-semibold text-[#1F2937]">{{ $value }}</p>
                                        </div>
                                        <p class="text-[10.5px] text-[#64748B] leading-snug">{{ $opt['desc'] }}</p>
                                    </label>
                                @endforeach
                            </div>
                            @error('availability_status')
                                <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-[12px] font-semibold text-[#1F2937]">Description</label>
                                <span class="text-[11px] text-[#64748B]" x-text="description.length + ' / 300'"></span>
                            </div>
                            <textarea name="description" rows="3" maxlength="300" x-model="description"
                                placeholder="Add any note or description about this unit..."
                                class="w-full rounded-xl border border-[#64748B]/30 px-3.5 py-2.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition resize-none"></textarea>
                            @error('description')
                                <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    {{-- Unit Photos --}}
                    <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-6"
                        x-data="{ tab: 'live' }">
                        <div class="flex items-center gap-2.5 mb-3">
                            <div class="w-8 h-8 rounded-lg bg-[#156F8C] flex items-center justify-center shrink-0">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-[13px] font-bold text-[#1F2937]">Unit Photos <span class="text-[#EF4444]">*</span></h2>
                                <p class="text-[11px] text-[#64748B] mt-0.5">Capture at least 3 live photos at the unit. Uploads are optional extras.</p>
                            </div>
                        </div>

                        <div class="mb-4 px-3.5 py-3 rounded-xl bg-[#EEF8F8] border border-[#2AA7A1]/20 flex items-start gap-2.5">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="2" class="shrink-0 mt-0.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                            </svg>
                            <p class="text-[12px] text-[#1F2937]/70 leading-relaxed">
                                Live photos prove the unit is real and current. A minimum of <strong>3 live captures</strong> is required — uploaded photos count as extras. Up to 10 photos total. You can add an optional caption to each.
                            </p>
                        </div>

                        {{-- Tabs --}}
                        <div class="inline-flex rounded-xl border border-[#64748B]/25 bg-[#F7FCFC] p-1 mb-4">
                            <button type="button" x-on:click="tab = 'live'"
                                :class="tab === 'live' ? 'bg-white shadow-sm text-[#156F8C]' : 'text-[#64748B]'"
                                class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-[12.5px] font-semibold transition-colors duration-150 cursor-pointer">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                                </svg>
                                Live Capture
                            </button>
                            <button type="button" x-on:click="tab = 'upload'"
                                :class="tab === 'upload' ? 'bg-white shadow-sm text-[#156F8C]' : 'text-[#64748B]'"
                                class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-[12.5px] font-semibold transition-colors duration-150 cursor-pointer">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                </svg>
                                Upload
                            </button>
                        </div>

                        {{-- Live Capture panel --}}
                        <div x-show="tab === 'live'">
                            <div id="camera-shell" class="rounded-xl border border-[#64748B]/25 bg-[#0F172A] overflow-hidden relative aspect-video flex items-center justify-center">
                                <video id="camera-video" autoplay playsinline muted class="hidden w-full h-full object-cover"></video>
                                <canvas id="camera-canvas" class="hidden"></canvas>

                                {{-- Idle / start state --}}
                                <div id="camera-idle" class="text-center px-6">
                                    <svg width="34" height="34" fill="none" viewBox="0 0 24 24" stroke="#94A3B8" stroke-width="1.4" class="mx-auto mb-2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                                    </svg>
                                    <p class="text-[13px] font-semibold text-white">Camera is off</p>
                                    <p class="text-[11.5px] text-[#94A3B8] mt-0.5 mb-3">Enable your camera to capture live photos at the unit.</p>
                                    <button type="button" id="camera-enable"
                                        class="h-9 px-4 inline-flex items-center gap-1.5 rounded-full bg-[#2AA7A1] text-white text-[12.5px] font-semibold hover:brightness-95 transition-all duration-200">
                                        Enable camera
                                    </button>
                                </div>

                                {{-- Capture button (live) --}}
                                <button type="button" id="camera-capture"
                                    class="hidden absolute bottom-3 left-1/2 -translate-x-1/2 w-14 h-14 rounded-full bg-white ring-4 ring-white/40 hover:brightness-95 transition-all flex items-center justify-center"
                                    aria-label="Capture photo">
                                    <span class="w-10 h-10 rounded-full border-2 border-[#156F8C]"></span>
                                </button>
                            </div>
                            <p id="camera-error" class="hidden text-[11.5px] text-[#EF4444] mt-2"></p>
                        </div>

                        {{-- Upload panel --}}
                        <div x-show="tab === 'upload'" x-cloak>
                            <div id="photo-dropzone"
                                class="rounded-xl border-2 border-dashed border-[#64748B]/30 bg-[#F7FCFC] px-6 py-8 text-center cursor-pointer hover:border-[#2AA7A1]/60 transition-colors duration-200">
                                <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="1.5" class="mx-auto mb-2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                                </svg>
                                <p class="text-[13px] font-semibold text-[#1F2937]">Click to select photos</p>
                                <p class="text-[11.5px] text-[#64748B] mt-0.5">JPEG, PNG or WEBP — extras beyond your 3 live photos</p>
                                <input type="file" id="upload-input" multiple accept="image/jpeg,image/png,image/webp" class="hidden" aria-label="Select additional unit photos">
                            </div>
                        </div>

                        {{-- Live counter --}}
                        <div class="mt-4 flex items-center gap-2 text-[12px]">
                            <span id="live-count-badge" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-semibold bg-[#FBBF24]/[0.10] text-[#B45309] border border-[#FBBF24]/35">
                                <span id="live-count">0</span> / 3 live photos
                            </span>
                            <span id="total-count" class="text-[#64748B]">0 total</span>
                        </div>

                        {{-- Hidden aggregated file input for submission --}}
                        <input type="file" id="photo-input" name="photos[]" multiple class="hidden" aria-label="Unit photos">

                        <p id="photo-limit-msg" class="hidden text-[11.5px] text-[#EF4444] mt-2">You can add a maximum of 10 photos.</p>
                        @error('photos')
                            <p class="text-[11.5px] text-[#EF4444] mt-2">{{ $message }}</p>
                        @enderror
                        @error('photos.*')
                            <p class="text-[11.5px] text-[#EF4444] mt-2">{{ $message }}</p>
                        @enderror

                        {{-- Unified gallery (live + uploaded) --}}
                        <div id="photo-gallery" class="hidden grid-cols-1 sm:grid-cols-2 gap-3 mt-4"></div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-3">
                        <a href="{{ route('landlord.properties.units.index', $property) }}"
                            class="h-11 px-6 inline-flex items-center justify-center rounded-full border border-[#64748B]/30 text-[#1F2937] text-sm font-semibold hover:bg-[#EEF8F8] transition-colors duration-200">
                            Cancel
                        </a>
                        <button type="submit"
                            class="h-11 px-6 inline-flex items-center justify-center rounded-full bg-[#2AA7A1] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200">
                            Save Unit
                        </button>
                    </div>
                </div>

                {{-- ── Right rail: live preview + amenities ───────────────── --}}
                <div class="lg:col-span-5">
                    <div class="space-y-6">
                        <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
                            <div class="px-5 pt-5 pb-3 flex items-center gap-2 border-b border-[#E2E8F0]/70">
                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <h3 class="text-[13px] font-bold text-[#156F8C]">Live Preview</h3>
                                <span class="ml-auto text-[10.5px] font-medium text-[#64748B]">Updates as you type</span>
                            </div>

                            {{-- Image area --}}
                            <div class="aspect-[4/3] bg-[#EEF8F8] flex flex-col items-center justify-center text-[#64748B] border-b border-[#E2E8F0]/70">
                                <svg width="34" height="34" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                                <p class="text-[11px] mt-1.5">Photos appear here</p>
                            </div>

                            {{-- Body --}}
                            <div class="p-5 space-y-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-[15px] font-bold text-[#1F2937] truncate"
                                            x-text="unitLabel || 'Unit name'"
                                            :class="unitLabel ? '' : 'text-[#64748B] font-semibold italic'"></p>
                                        <p class="text-[12px] text-[#64748B] mt-0.5">
                                            <span x-text="unitType || 'Type not set'"></span><template x-if="floor"><span> · <span x-text="floor"></span></span></template>
                                        </p>
                                    </div>
                                    {{-- Status pill --}}
                                    <span class="shrink-0 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                                        :style="`border-color:${statusMeta[status].dot}55; background:${statusMeta[status].dot}14; color:#1F2937`">
                                        <span class="w-1.5 h-1.5 rounded-full" :style="`background:${statusMeta[status].dot}`"></span>
                                        <span x-text="status"></span>
                                    </span>
                                </div>

                                {{-- Rent --}}
                                <div class="flex items-baseline gap-1">
                                    <span class="text-[20px] font-bold text-[#156F8C]" x-text="peso(rentalFee) || '₱—'"></span>
                                    <span class="text-[12px] text-[#64748B]">/ month</span>
                                </div>

                                {{-- Meta rows --}}
                                <div class="grid grid-cols-2 gap-2 pt-1">
                                    <div class="rounded-lg bg-[#F7FCFC] border border-[#E2E8F0] px-3 py-2">
                                        <p class="text-[10px] uppercase tracking-wide text-[#64748B]">Capacity</p>
                                        <p class="text-[13px] font-semibold text-[#1F2937] mt-0.5"
                                            x-text="capacity ? capacity + (capacity == 1 ? ' person' : ' persons') : '—'"></p>
                                    </div>
                                    <div class="rounded-lg bg-[#F7FCFC] border border-[#E2E8F0] px-3 py-2">
                                        <p class="text-[10px] uppercase tracking-wide text-[#64748B]">Deposit</p>
                                        <p class="text-[13px] font-semibold text-[#1F2937] mt-0.5" x-text="peso(securityDeposit) || '—'"></p>
                                    </div>
                                </div>

                                {{-- Amenities --}}
                                <div x-show="amenities.length" x-cloak class="pt-1">
                                    <p class="text-[10px] uppercase tracking-wide text-[#64748B] mb-1.5">Amenities</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        <template x-for="id in amenities" :key="id">
                                            <span class="inline-flex items-center rounded-full bg-[#EEF8F8] border border-[#2AA7A1]/20 px-2 py-0.5 text-[11px] text-[#1F2937]"
                                                x-text="amenityNames[id]"></span>
                                        </template>
                                    </div>
                                </div>

                                {{-- Description --}}
                                <p class="text-[12px] text-[#64748B] leading-relaxed line-clamp-3 pt-1"
                                    x-show="description" x-cloak x-text="description"></p>
                            </div>
                        </div>

                        <p class="text-[11px] text-[#64748B] text-center px-4 leading-relaxed">
                            This is a preview of how the unit's key details will read to tenants once approved.
                        </p>

                        {{-- Unit Amenities --}}
                        <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] p-6">
                            <div class="flex items-center gap-2.5 mb-5">
                                <div class="w-8 h-8 rounded-lg bg-[#2AA7A1] flex items-center justify-center shrink-0">
                                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                                    </svg>
                                </div>
                                <h2 class="text-[13px] font-bold text-[#1F2937]">Unit Amenities</h2>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach($amenities as $amenity)
                                    <label class="flex items-center gap-2.5 rounded-lg border px-3 py-2.5 cursor-pointer transition-colors duration-150"
                                        :class="amenities.includes('{{ $amenity->amenity_id }}') ? 'border-[#2AA7A1] bg-[#EEF8F8]' : 'border-[#64748B]/25 bg-white hover:border-[#64748B]/40'">
                                        <input type="checkbox" name="amenities[]" value="{{ $amenity->amenity_id }}" x-model="amenities"
                                            class="w-4 h-4 rounded border-[#64748B]/40 text-[#2AA7A1] focus:ring-[#2AA7A1]/30">
                                        <span class="text-[12.5px] text-[#1F2937] leading-tight">{{ $amenity->name }}</span>
                                    </label>
                                @endforeach

                                {{-- Others --}}
                                <div x-data="{ others: false }" class="contents">
                                    <label class="flex items-center gap-2.5 rounded-lg border px-3 py-2.5 cursor-pointer transition-colors duration-150"
                                        :class="others ? 'border-[#2AA7A1] bg-[#EEF8F8]' : 'border-[#64748B]/25 bg-white hover:border-[#64748B]/40'">
                                        <input type="checkbox" x-model="others"
                                            class="w-4 h-4 rounded border-[#64748B]/40 text-[#2AA7A1] focus:ring-[#2AA7A1]/30">
                                        <span class="text-[12.5px] text-[#1F2937] leading-tight">Others</span>
                                    </label>
                                    <div x-show="others" x-cloak class="col-span-full">
                                        <input type="text" placeholder="Specify other amenity..." aria-label="Specify other amenity"
                                            class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                                    </div>
                                </div>
                            </div>
                            @error('amenities')
                                <p class="text-[11.5px] text-[#EF4444] mt-2">{{ $message }}</p>
                            @enderror
                            @error('amenities.*')
                                <p class="text-[11.5px] text-[#EF4444] mt-2">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

            </div>
        </form>

    </div>

    <script>
        (function () {
            const MAX_PHOTOS = 10;
            const MIN_LIVE = 3;

            const photoInput = document.getElementById('photo-input');
            const gallery = document.getElementById('photo-gallery');
            const limitMsg = document.getElementById('photo-limit-msg');
            const liveCountEl = document.getElementById('live-count');
            const totalCountEl = document.getElementById('total-count');
            const liveBadge = document.getElementById('live-count-badge');

            // Upload
            const dropzone = document.getElementById('photo-dropzone');
            const uploadInput = document.getElementById('upload-input');

            // Camera
            const video = document.getElementById('camera-video');
            const canvas = document.getElementById('camera-canvas');
            const idle = document.getElementById('camera-idle');
            const enableBtn = document.getElementById('camera-enable');
            const captureBtn = document.getElementById('camera-capture');
            const cameraError = document.getElementById('camera-error');

            let photos = []; // { id, file, source, caption, url }
            let stream = null;

            const newId = () => (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : String(Math.random());

            // ── Upload ──────────────────────────────────────────────
            dropzone.addEventListener('click', () => uploadInput.click());
            uploadInput.addEventListener('change', () => {
                for (const file of uploadInput.files) addPhoto(file, 'upload');
                uploadInput.value = '';
            });

            // ── Camera ──────────────────────────────────────────────
            enableBtn.addEventListener('click', async () => {
                cameraError.classList.add('hidden');
                try {
                    stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: { ideal: 'environment' } }, audio: false
                    });
                    video.srcObject = stream;
                    idle.classList.add('hidden');
                    video.classList.remove('hidden');
                    captureBtn.classList.remove('hidden');
                } catch (e) {
                    cameraError.textContent = 'Could not access the camera (' + (e.name || 'error') + '). Check browser permissions, or use the Upload tab instead.';
                    cameraError.classList.remove('hidden');
                }
            });

            captureBtn.addEventListener('click', () => {
                if (photos.length >= MAX_PHOTOS) { showLimit(); return; }
                const w = video.videoWidth, h = video.videoHeight;
                if (!w || !h) return;
                canvas.width = w; canvas.height = h;
                canvas.getContext('2d').drawImage(video, 0, 0, w, h);
                canvas.toBlob((blob) => {
                    if (!blob) return;
                    const file = new File([blob], 'live-' + Date.now() + '.jpg', { type: 'image/jpeg' });
                    addPhoto(file, 'camera');
                }, 'image/jpeg', 0.9);
            });

            window.addEventListener('beforeunload', () => {
                if (stream) stream.getTracks().forEach(t => t.stop());
            });

            // ── Shared ──────────────────────────────────────────────
            function addPhoto(file, source) {
                if (photos.length >= MAX_PHOTOS) { showLimit(); return; }
                limitMsg.classList.add('hidden');
                photos.push({ id: newId(), file, source, caption: '', url: URL.createObjectURL(file) });
                render();
            }

            function removePhoto(id) {
                const i = photos.findIndex(p => p.id === id);
                if (i === -1) return;
                URL.revokeObjectURL(photos[i].url);
                photos.splice(i, 1);
                limitMsg.classList.add('hidden');
                render();
            }

            function showLimit() {
                limitMsg.classList.remove('hidden');
            }

            function syncInput() {
                const dt = new DataTransfer();
                photos.forEach(p => dt.items.add(p.file));
                photoInput.files = dt.files;
            }

            function updateCounters() {
                const live = photos.filter(p => p.source === 'camera').length;
                liveCountEl.textContent = live;
                totalCountEl.textContent = photos.length + ' total';
                const ok = live >= MIN_LIVE;
                liveBadge.className = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-semibold border '
                    + (ok ? 'bg-[#22C55E]/[0.07] text-[#15803D] border-[#22C55E]/25' : 'bg-[#FBBF24]/[0.10] text-[#B45309] border-[#FBBF24]/35');
            }

            function render() {
                // Rebuild aggregated file input in the same order as the gallery
                syncInput();

                gallery.innerHTML = '';
                gallery.classList.toggle('hidden', photos.length === 0);
                gallery.classList.toggle('grid', photos.length > 0);

                photos.forEach((p) => {
                    const card = document.createElement('div');
                    card.className = 'rounded-xl overflow-hidden border border-[#E2E8F0] bg-white';
                    card.innerHTML =
                        '<div class="relative aspect-video bg-[#F7FCFC]">'
                        + '<img src="' + p.url + '" alt="Unit photo" class="w-full h-full object-cover">'
                        + '<span class="absolute top-1.5 left-1.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold '
                        + (p.source === 'camera' ? 'bg-[#2AA7A1] text-white' : 'bg-white/90 text-[#1F2937] border border-[#E2E8F0]')
                        + '">' + (p.source === 'camera' ? 'Live' : 'Upload') + '</span>'
                        + '<input type="hidden" name="photo_sources[]" value="' + p.source + '">'
                        + '</div>'
                        + '<div class="p-2">'
                        + '<input type="text" name="photo_captions[]" maxlength="150" placeholder="Add a caption (optional)" aria-label="Photo caption (optional)" '
                        + 'class="h-9 w-full rounded-lg border border-[#64748B]/25 px-2.5 text-[12px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">'
                        + '</div>';

                    // Remove button
                    const rm = document.createElement('button');
                    rm.type = 'button';
                    rm.className = 'absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-white/90 border border-[#E2E8F0] flex items-center justify-center text-[#EF4444] hover:brightness-95 transition';
                    rm.setAttribute('aria-label', 'Remove photo');
                    rm.innerHTML = '<svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>';
                    rm.addEventListener('click', () => removePhoto(p.id));
                    card.querySelector('.relative').appendChild(rm);

                    // Caption (submitted directly; keep model in sync without re-render)
                    const cap = card.querySelector('input[name="photo_captions[]"]');
                    cap.value = p.caption;
                    cap.addEventListener('input', () => { p.caption = cap.value; });

                    gallery.appendChild(card);
                });

                updateCounters();
            }

            updateCounters();
        })();
    </script>
@endsection
