@extends('layouts.landlord')

@section('content')
    <div class="max-w-[900px] mx-auto px-4 sm:px-8 lg:px-[50px] py-8">

        {{-- Back link --}}
        <a href="{{ route('landlord.profile.me') }}"
            class="inline-flex items-center gap-1.5 text-[13px] font-medium text-[#64748B] hover:text-[#1F2937] transition-colors mb-6">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
            Back to profile
        </a>

        <h1 class="text-[22px] font-bold text-[#1F2937] mb-6">Edit profile</h1>

        {{-- Validation errors --}}
        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-[13px] font-medium mb-6">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('landlord.profile.update') }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PATCH')

            {{-- Profile picture --}}
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-5 mb-5">
                <h2 class="text-[15px] font-bold text-[#1F2937] mb-4">Profile picture</h2>
                <div class="flex items-center gap-5">
                    @if($user->profile_picture)
                        <img src="{{ $user->profile_picture }}" alt="{{ $user->first_name }}"
                            class="w-20 h-20 rounded-full object-cover">
                    @else
                        <div
                            class="w-20 h-20 rounded-full bg-[#2AA7A1] flex items-center justify-center text-white text-[28px] font-bold">
                            {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
                        </div>
                    @endif
                    <div>
                        <label
                            class="inline-flex items-center gap-2 px-4 py-2 border border-[#E2E8F0] rounded-lg text-[13px] font-semibold text-[#1F2937] bg-white hover:brightness-95 transition-all cursor-pointer">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                            Upload photo
                            <input type="file" name="profile_picture" accept="image/*" class="hidden">
                        </label>
                        <p class="text-[11px] text-[#64748B] mt-2">JPG, PNG. Max 2MB. Will be cropped to square.</p>
                    </div>
                </div>
            </div>

            {{-- Personal info --}}
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-5 mb-5">
                <h2 class="text-[15px] font-bold text-[#1F2937] mb-4">Personal information</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="first_name" class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">First name</label>
                        <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $user->first_name) }}" required
                            class="w-full px-3.5 py-2.5 bg-white border border-[#E2E8F0] rounded-lg text-[14px] text-[#1F2937] placeholder-[#64748B]/50 focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all">
                    </div>
                    <div>
                        <label for="last_name" class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">Last name</label>
                        <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $user->last_name) }}" required
                            class="w-full px-3.5 py-2.5 bg-white border border-[#E2E8F0] rounded-lg text-[14px] text-[#1F2937] placeholder-[#64748B]/50 focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="contact_number" class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">Contact number</label>
                    <input type="text" id="contact_number" name="contact_number" value="{{ old('contact_number', $user->contact_number) }}"
                        class="w-full px-3.5 py-2.5 bg-white border border-[#E2E8F0] rounded-lg text-[14px] text-[#1F2937] placeholder-[#64748B]/50 focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all"
                        placeholder="09XX XXX XXXX">
                </div>

                <div class="mb-4">
                    <label for="email" class="block text-[13px] font-semibold text-[#64748B] mb-1.5">Email address</label>
                    <input type="email" id="email" value="{{ $user->email }}" disabled
                        class="w-full px-3.5 py-2.5 bg-[#F7FCFC] border border-[#E2E8F0] rounded-lg text-[14px] text-[#64748B] cursor-not-allowed">
                    <p class="text-[11px] text-[#64748B] mt-1.5">Email can be changed in <a
                            href="{{ route('profile.edit') }}" class="text-[#156F8C] hover:underline">Settings</a>.</p>
                </div>

                <div>
                    <label class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">Bio</label>
                    <div x-data="{ count: {{ strlen(old('bio', $user->bio ?? '')) }} }">
                        <textarea name="bio" rows="3" maxlength="1000" x-on:input="count = $event.target.value.length"
                            class="w-full px-3.5 py-2.5 bg-white border border-[#E2E8F0] rounded-lg text-[14px] text-[#1F2937] placeholder-[#64748B]/50 focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all resize-none"
                            placeholder="Tell tenants a bit about yourself...">{{ old('bio', $user->bio) }}</textarea>
                        <p class="text-[11px] text-[#64748B] mt-1 text-right"><span x-text="count">0</span> / 1,000</p>
                    </div>
                </div>
            </div>

            {{-- Business info --}}
            <div class="bg-white/70 backdrop-blur-xl border border-white/30 rounded-2xl shadow-lg p-5 mb-5">
                <h2 class="text-[15px] font-bold text-[#1F2937] mb-1">Business information</h2>
                <p class="text-[12px] text-[#64748B] mb-4">Shown on your public landlord profile.</p>

                {{-- Logo --}}
                <div x-data="{ preview: null }" class="flex items-center gap-5 mb-5 pb-5 border-b border-[#E2E8F0]">
                    <template x-if="preview">
                        <img :src="preview" alt="Logo preview"
                            class="w-16 h-16 rounded-xl object-cover border border-[#E2E8F0]">
                    </template>
                    <template x-if="!preview">
                        @if($business && $business->logo_url)
                            <img src="{{ $business->logo_url }}" alt="{{ $business->business_name ?? 'Logo' }}"
                                class="w-16 h-16 rounded-xl object-cover border border-[#E2E8F0]">
                        @else
                            <div
                                class="w-16 h-16 rounded-xl bg-[#EEF8F8] border border-[#E2E8F0] flex items-center justify-center">
                                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#64748B" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z" />
                                </svg>
                            </div>
                        @endif
                    </template>
                    <div>
                        <label
                            class="inline-flex items-center gap-2 px-3 py-1.5 border border-[#E2E8F0] rounded-lg text-[12px] font-semibold text-[#1F2937] bg-white hover:brightness-95 transition-all cursor-pointer">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                            Upload logo
                            <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="hidden"
                                x-on:change="if ($event.target.files[0]) { preview = URL.createObjectURL($event.target.files[0]) }">
                        </label>
                        <p class="text-[11px] text-[#64748B] mt-1.5">JPG, PNG, or WebP. Max 2MB.</p>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="business_name" class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">Business name</label>
                    <input type="text" id="business_name" name="business_name"
                        value="{{ old('business_name', $business->business_name ?? '') }}"
                        class="w-full px-3.5 py-2.5 bg-white border border-[#E2E8F0] rounded-lg text-[14px] text-[#1F2937] placeholder-[#64748B]/50 focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all"
                        placeholder="e.g. Santos Rentals">
                </div>

                <div class="mb-4">
                    <label class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">Business description</label>
                    <textarea name="business_description" rows="2" maxlength="1000"
                        class="w-full px-3.5 py-2.5 bg-white border border-[#E2E8F0] rounded-lg text-[14px] text-[#1F2937] placeholder-[#64748B]/50 focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all resize-none"
                        placeholder="Briefly describe your rental business...">{{ old('business_description', $business->description ?? '') }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="business_contact" class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">Business contact</label>
                        <input type="text" id="business_contact" name="business_contact"
                            value="{{ old('business_contact', $business->contact_number ?? '') }}"
                            class="w-full px-3.5 py-2.5 bg-white border border-[#E2E8F0] rounded-lg text-[14px] text-[#1F2937] placeholder-[#64748B]/50 focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all"
                            placeholder="09XX XXX XXXX">
                    </div>
                    <div>
                        <label for="business_address" class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">Business address</label>
                        <input type="text" id="business_address" name="business_address"
                            value="{{ old('business_address', $business->business_address ?? '') }}"
                            class="w-full px-3.5 py-2.5 bg-white border border-[#E2E8F0] rounded-lg text-[14px] text-[#1F2937] placeholder-[#64748B]/50 focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all"
                            placeholder="Cebu City, Cebu">
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('landlord.profile.me') }}"
                    class="px-5 py-2.5 border border-[#E2E8F0] rounded-lg text-[13px] font-semibold text-[#1F2937] bg-white hover:brightness-95 transition-all">
                    Cancel
                </a>
                <button type="submit"
                    class="px-5 py-2.5 bg-[#2AA7A1] text-white rounded-lg text-[13px] font-semibold hover:brightness-95 transition-all shadow-sm">
                    Save changes
                </button>
            </div>
        </form>

    </div>
@endsection