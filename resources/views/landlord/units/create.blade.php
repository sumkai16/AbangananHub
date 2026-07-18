@extends('layouts.landlord')

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8 pb-16">

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
            <div class="mb-6 px-4 py-3 rounded-xl bg-red-50 border border-red-100 text-red-700 text-sm font-medium flex items-start gap-2.5">
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

        <form method="POST" action="{{ route('landlord.properties.units.store', $property) }}" enctype="multipart/form-data"
            class="max-w-3xl space-y-6">
            @csrf

            {{-- Property Information (read-only) --}}
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-6">
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
                        <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Rental Business</label>
                        <input type="text" value="{{ $property->rentalBusiness->business_name ?? 'N/A' }}" disabled
                            class="h-11 w-full rounded-xl border border-[#64748B]/30 bg-[#EEF8F8] px-3.5 text-[13.5px] text-[#64748B] cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Property</label>
                        <input type="text" value="{{ $property->title . ' - ' . $property->address }}" disabled
                            class="h-11 w-full rounded-xl border border-[#64748B]/30 bg-[#EEF8F8] px-3.5 text-[13.5px] text-[#64748B] cursor-not-allowed">
                    </div>
                </div>
            </div>

            {{-- Unit Details --}}
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-6">
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
                        <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                            Unit Name / Number <span class="text-[#EF4444]">*</span>
                        </label>
                        <input type="text" name="unit_label" value="{{ old('unit_label') }}" required maxlength="100"
                            placeholder="e.g. Room 101, Bed A"
                            class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                        @error('unit_label')
                            <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Unit Type</label>
                        <select name="unit_type"
                            class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3 text-[13.5px] text-[#1F2937] bg-white focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                            <option value="">Select type</option>
                            @foreach(['Bedspace', 'Room', 'Apartment', 'Studio', 'Dormitory'] as $type)
                                <option value="{{ $type }}" {{ old('unit_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('unit_type')
                            <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Floor</label>
                        <input type="text" name="floor" value="{{ old('floor') }}" maxlength="50"
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
                        <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                            Monthly Rent (₱) <span class="text-[#EF4444]">*</span>
                        </label>
                        <input type="number" name="rental_fee" value="{{ old('rental_fee') }}" required min="500"
                            max="999999.99" step="0.01" placeholder="e.g. 3500"
                            class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                        @error('rental_fee')
                            <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">Security Deposit (₱)</label>
                        <input type="number" name="security_deposit" value="{{ old('security_deposit') }}" min="0"
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
                        <select name="occupancy_limit" required
                            class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3 text-[13.5px] text-[#1F2937] bg-white focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                            <option value="">Select capacity</option>
                            @for($i = 1; $i <= 20; $i++)
                                <option value="{{ $i }}" {{ (string) old('occupancy_limit') === (string) $i ? 'selected' : '' }}>
                                    {{ $i }} {{ $i === 1 ? 'person' : 'persons' }}
                                </option>
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
                            'Available' => ['desc' => 'Unit is vacant and ready', 'dot' => 'bg-[#22C55E]', 'active' => 'border-emerald-300 bg-emerald-50'],
                            'Reserved' => ['desc' => 'On hold for a tenant', 'dot' => 'bg-[#FBBF24]', 'active' => 'border-amber-300 bg-amber-50'],
                            'Occupied' => ['desc' => 'Currently rented', 'dot' => 'bg-[#EF4444]', 'active' => 'border-red-300 bg-red-50'],
                            'Maintenance' => ['desc' => 'Temporarily unavailable', 'dot' => 'bg-[#64748B]', 'active' => 'border-slate-300 bg-slate-50'],
                        ];
                        $inactiveClass = 'border-[#64748B]/25 bg-white hover:border-[#64748B]/40';
                        $currentStatus = old('availability_status', 'Available');
                    @endphp
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2" x-data="{ status: '{{ $currentStatus }}' }">
                        @foreach($statusOptions as $value => $opt)
                            <label class="relative cursor-pointer rounded-xl border px-3 py-3 transition-colors duration-150"
                                :class="status === '{{ $value }}' ? '{{ $opt['active'] }}' : '{{ $inactiveClass }}'">
                                <input type="radio" name="availability_status" value="{{ $value }}" x-model="status"
                                    class="sr-only" {{ $currentStatus === $value ? 'checked' : '' }}>
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
                <div x-data="{ count: {{ strlen(old('description', '')) }} }">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-[12px] font-semibold text-[#1F2937]">Description</label>
                        <span class="text-[11px] text-[#64748B]" x-text="count + ' / 300'"></span>
                    </div>
                    <textarea name="description" rows="3" maxlength="300"
                        placeholder="Add any note or description about this unit..."
                        x-on:input="count = $event.target.value.length"
                        class="w-full rounded-xl border border-[#64748B]/30 px-3.5 py-2.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition resize-none">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="text-[11.5px] text-[#EF4444] mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Unit Amenities --}}
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-6">
                <div class="flex items-center gap-2.5 mb-5">
                    <div class="w-8 h-8 rounded-lg bg-[#2AA7A1] flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                        </svg>
                    </div>
                    <h2 class="text-[13px] font-bold text-[#1F2937]">Unit Amenities</h2>
                </div>

                @php $oldAmenities = collect(old('amenities', []))->map(fn ($id) => (string) $id)->all(); @endphp
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                    @foreach($amenities as $amenity)
                        <label class="flex items-center gap-2.5 rounded-lg border px-3 py-2.5 cursor-pointer transition-colors duration-150
                            {{ in_array((string) $amenity->amenity_id, $oldAmenities, true) ? 'border-[#2AA7A1] bg-[#EEF8F8]' : 'border-[#64748B]/25 bg-white hover:border-[#64748B]/40' }}"
                            x-data x-bind:class="$refs.cb.checked ? 'border-[#2AA7A1] bg-[#EEF8F8]' : 'border-[#64748B]/25 bg-white hover:border-[#64748B]/40'">
                            <input type="checkbox" name="amenities[]" value="{{ $amenity->amenity_id }}" x-ref="cb"
                                {{ in_array((string) $amenity->amenity_id, $oldAmenities, true) ? 'checked' : '' }}
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
                            <input type="text" placeholder="Specify other amenity..."
                                class="h-11 w-full sm:max-w-xs rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
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

            {{-- Unit Photos --}}
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-6">
                <div class="flex items-center gap-2.5 mb-3">
                    <div class="w-8 h-8 rounded-lg bg-[#156F8C] flex items-center justify-center shrink-0">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-[13px] font-bold text-[#1F2937]">Unit Photos (Optional)</h2>
                        <p class="text-[11px] text-[#64748B] mt-0.5">Upload photos of this unit. (Max. 8 images)</p>
                    </div>
                </div>

                <div class="mb-4 px-3.5 py-3 rounded-xl bg-[#EEF8F8] border border-[#2AA7A1]/20 flex items-start gap-2.5">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="2" class="shrink-0 mt-0.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                    </svg>
                    <p class="text-[12px] text-[#1F2937]/70 leading-relaxed">
                        If you add photos, a minimum of 3 is required. Accepted formats: JPEG, PNG, WEBP. Up to 8 images.
                    </p>
                </div>

                <div id="photo-dropzone"
                    class="rounded-xl border-2 border-dashed border-[#64748B]/30 bg-white/50 px-6 py-8 text-center cursor-pointer hover:border-[#2AA7A1]/60 transition-colors duration-200">
                    <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="1.5" class="mx-auto mb-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                    </svg>
                    <p class="text-[13px] font-semibold text-[#1F2937]">Click to select photos</p>
                    <p class="text-[11.5px] text-[#64748B] mt-0.5">JPEG, PNG or WEBP — max 8 images</p>
                    <input type="file" id="photo-input" name="photos[]" multiple accept="image/jpeg,image/png,image/webp"
                        class="hidden">
                </div>
                <p id="photo-limit-msg" class="hidden text-[11.5px] text-[#EF4444] mt-2">You can upload a maximum of 8 photos.</p>
                @error('photos')
                    <p class="text-[11.5px] text-[#EF4444] mt-2">{{ $message }}</p>
                @enderror
                @error('photos.*')
                    <p class="text-[11.5px] text-[#EF4444] mt-2">{{ $message }}</p>
                @enderror

                <div id="photo-preview" class="hidden grid-cols-3 gap-3 mt-4"></div>
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
        </form>

    </div>

    <script>
        (function () {
            const MAX_PHOTOS = 8;
            const dropzone = document.getElementById('photo-dropzone');
            const input = document.getElementById('photo-input');
            const preview = document.getElementById('photo-preview');
            const limitMsg = document.getElementById('photo-limit-msg');
            let files = [];

            dropzone.addEventListener('click', () => input.click());

            input.addEventListener('change', () => {
                limitMsg.classList.add('hidden');
                for (const file of input.files) {
                    if (files.length >= MAX_PHOTOS) {
                        limitMsg.classList.remove('hidden');
                        break;
                    }
                    files.push(file);
                }
                syncInput();
                renderPreviews();
            });

            function syncInput() {
                const dt = new DataTransfer();
                files.forEach(f => dt.items.add(f));
                input.files = dt.files;
            }

            function renderPreviews() {
                preview.innerHTML = '';
                preview.classList.toggle('hidden', files.length === 0);
                preview.classList.toggle('grid', files.length > 0);
                files.forEach((file, index) => {
                    const wrap = document.createElement('div');
                    wrap.className = 'relative rounded-xl overflow-hidden border border-[#E2E8F0] aspect-square';

                    const img = document.createElement('img');
                    img.className = 'w-full h-full object-cover';
                    img.alt = 'Unit photo preview';
                    img.src = URL.createObjectURL(file);
                    img.onload = () => URL.revokeObjectURL(img.src);

                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-white/90 border border-[#E2E8F0] flex items-center justify-center text-[#EF4444] hover:brightness-95 transition';
                    btn.setAttribute('aria-label', 'Remove photo');
                    btn.innerHTML = '<svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>';
                    btn.addEventListener('click', () => {
                        files.splice(index, 1);
                        limitMsg.classList.add('hidden');
                        syncInput();
                        renderPreviews();
                    });

                    wrap.appendChild(img);
                    wrap.appendChild(btn);
                    preview.appendChild(wrap);
                });
            }
        })();
    </script>
@endsection
