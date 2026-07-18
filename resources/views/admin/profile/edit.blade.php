@extends('layouts.admin')

@section('page-title', 'My Profile')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    {{-- Stats Row --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @foreach ([
            ['Total Users', $stats['total_users'], 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z'],
            ['My Reviews', $stats['verifications_reviewed'], 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z'],
            ['Properties Approved', $stats['properties_approved'], 'M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25'],
        ] as [$label, $value, $icon])
            <div class="bg-white/70 backdrop-blur-xl rounded-2xl border border-white/30 shadow-lg px-5 py-4 flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#2AA7A1" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                    </svg>
                </div>
                <div>
                    <p class="text-2xl font-bold text-[#1F2937] leading-none">{{ $value }}</p>
                    <p class="text-[13px] text-[#64748B] mt-0.5">{{ $label }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Two-Column Layout --}}
    <div class="grid grid-cols-1 lg:grid-cols-[240px_1fr] gap-6 items-start">

        {{-- Left Column: Avatar Card --}}
        <div class="lg:sticky lg:top-24 bg-white/70 backdrop-blur-xl rounded-2xl border border-white/30 shadow-lg p-6 text-center">
            <form id="avatar-form" action="{{ route('admin.profile.update') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PATCH')
                <input type="hidden" name="first_name" value="{{ $user->first_name }}" />
                <input type="hidden" name="last_name" value="{{ $user->last_name }}" />

                <div class="relative group mx-auto w-20 h-20 mb-4">
                    @if ($user->profile_picture)
                        <img src="{{ $user->profile_picture }}" alt="Profile"
                             class="w-20 h-20 rounded-2xl object-cover border-2 border-[#E2E8F0]" />
                    @else
                        <div class="w-20 h-20 rounded-2xl bg-[#2AA7A1] flex items-center justify-center border-2 border-[#E2E8F0]">
                            <span class="text-2xl font-bold text-white">
                                {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
                            </span>
                        </div>
                    @endif
                    <label class="absolute inset-0 flex items-center justify-center bg-black/40 rounded-2xl opacity-0 group-hover:opacity-100 cursor-pointer transition-opacity">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                        </svg>
                        <input type="file" name="profile_picture" accept="image/*" class="hidden"
                               onchange="document.getElementById('avatar-form').submit()" />
                    </label>
                </div>

                @error('profile_picture')
                    <p class="text-[12px] text-[#EF4444] mb-2">{{ $message }}</p>
                @enderror
            </form>

            <h3 class="text-[15px] font-bold text-[#1F2937]">{{ $user->first_name }} {{ $user->last_name }}</h3>
            <p class="text-[13px] text-[#64748B] mt-1">Administrator</p>
            <p class="text-[12px] text-[#64748B] mt-0.5">Member since {{ $user->created_at->format('F Y') }}</p>

            <div class="mt-4 pt-4 border-t border-[#E2E8F0]">
                <p class="text-[12px] text-[#64748B]">Hover the avatar to change your photo</p>
            </div>
        </div>

        {{-- Right Column: Forms --}}
        <div class="space-y-6">

            {{-- Profile Form --}}
            <form action="{{ route('admin.profile.update') }}" method="POST"
                  class="bg-white/70 backdrop-blur-xl rounded-2xl border border-white/30 shadow-lg divide-y divide-gray-100">
                @csrf
                @method('PATCH')

                <div class="p-6 space-y-5">
                    <h4 class="text-[15px] font-bold text-[#1F2937]">Personal Information</h4>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label for="first_name" class="block text-[13px] font-medium text-[#1F2937] mb-1.5">First Name</label>
                            <input type="text" name="first_name" id="first_name"
                                   value="{{ old('first_name', $user->first_name) }}"
                                   class="w-full rounded-xl border border-[#E2E8F0] bg-white px-4 py-2.5 text-[14px] text-[#1F2937] placeholder-[#94A3B8] focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] transition-colors" />
                            @error('first_name')
                                <p class="text-[13px] text-[#EF4444] mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="last_name" class="block text-[13px] font-medium text-[#1F2937] mb-1.5">Last Name</label>
                            <input type="text" name="last_name" id="last_name"
                                   value="{{ old('last_name', $user->last_name) }}"
                                   class="w-full rounded-xl border border-[#E2E8F0] bg-white px-4 py-2.5 text-[14px] text-[#1F2937] placeholder-[#94A3B8] focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] transition-colors" />
                            @error('last_name')
                                <p class="text-[13px] text-[#EF4444] mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="contact_number" class="block text-[13px] font-medium text-[#1F2937] mb-1.5">Contact Number</label>
                        <input type="text" name="contact_number" id="contact_number"
                               value="{{ old('contact_number', $user->contact_number) }}"
                               placeholder="e.g. 09171234567"
                               class="w-full rounded-xl border border-[#E2E8F0] bg-white px-4 py-2.5 text-[14px] text-[#1F2937] placeholder-[#94A3B8] focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] transition-colors" />
                        @error('contact_number')
                            <p class="text-[13px] text-[#EF4444] mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-[13px] font-medium text-[#1F2937] mb-1.5">Email Address</label>
                        <input type="email" value="{{ $user->email }}" disabled
                               class="w-full rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] px-4 py-2.5 text-[14px] text-[#64748B] cursor-not-allowed" />
                        <p class="text-[12px] text-[#64748B] mt-1">Email can be changed in the password section below.</p>
                    </div>

                    <div x-data="{ count: {{ Js::from(strlen(old('bio', $user->bio ?? ''))) }} }">
                        <label for="bio" class="block text-[13px] font-medium text-[#1F2937] mb-1.5">Bio</label>
                        <textarea name="bio" id="bio" rows="4" maxlength="1000"
                                  x-on:input="count = $el.value.length"
                                  placeholder="A short bio about yourself..."
                                  class="w-full rounded-xl border border-[#E2E8F0] bg-white px-4 py-2.5 text-[14px] text-[#1F2937] placeholder-[#94A3B8] focus:border-[#2AA7A1] focus:ring-1 focus:ring-[#2AA7A1] transition-colors resize-none">{{ old('bio', $user->bio) }}</textarea>
                        <p class="text-[12px] text-[#64748B] mt-1 text-right"><span x-text="count"></span> / 1,000</p>
                        @error('bio')
                            <p class="text-[13px] text-[#EF4444] mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="p-6 flex items-center justify-end gap-3">
                    <a href="{{ route('admin.dashboard') }}"
                       class="px-5 py-2.5 rounded-xl border border-[#E2E8F0] text-[13px] font-medium text-[#64748B] hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-6 py-2.5 rounded-xl bg-[#2AA7A1] text-white text-[13px] font-semibold hover:brightness-95 transition-all">
                        Save Changes
                    </button>
                </div>
            </form>

            {{-- Password --}}
            <div class="bg-white/70 backdrop-blur-xl rounded-2xl border border-white/30 shadow-lg p-6">
                @include('profile.partials.update-password-form')
            </div>

            {{-- Danger Zone --}}
            <div class="bg-white/70 backdrop-blur-xl rounded-2xl border border-white/30 shadow-lg p-6">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</div>
@endsection