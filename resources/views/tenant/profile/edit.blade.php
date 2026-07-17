@extends('layouts.app')

@section('hide_search', true)

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-[50px] py-8 min-h-[calc(100vh-72px)]">

        {{-- Back link + heading --}}
        <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-x-6 gap-y-0">

            {{-- Top row: back link + heading aligned to grid columns --}}
            <div class="hidden lg:block ">
                <a href="{{ route('tenant.profile.show') }}"
                    class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-[#64748B] hover:text-[#1F2937] transition-colors">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                    Back to profile
                </a>
            </div>
            <div class="">
                <a href="{{ route('tenant.profile.show') }}"
                    class="lg:hidden inline-flex items-center gap-1.5 text-[13px] font-semibold text-[#64748B] hover:text-[#1F2937] transition-colors mb-4">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                    Back to profile
                </a>
                <h1 class="text-[22px] font-bold text-[#1F2937] mb-5">Edit profile</h1>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-6">
            {{-- Left: profile preview sidebar --}}
            <div class="hidden lg:block">
                <div class="bg-white/70 backdrop-blur-xl rounded-2xl border border-white/30 shadow-lg p-6 text-center sticky top-[90px]">
                    <div id="sidebar-avatar" class="mx-auto mb-4">
                        @if($user->profile_picture)
                            <img src="{{ $user->profile_picture }}" alt="{{ $user->first_name }}"
                                class="w-24 h-24 rounded-full object-cover mx-auto">
                        @else
                            <div
                                class="w-24 h-24 rounded-full bg-[#2AA7A1] flex items-center justify-center text-white text-[32px] font-bold mx-auto">
                                {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
                            </div>
                        @endif
                    </div>
                    <p class="text-[16px] font-bold text-[#1F2937]">{{ $user->first_name }} {{ $user->last_name }}</p>
                    <p class="text-[12px] text-[#64748B] mt-1">{{ $user->email }}</p>

                    <div class="flex justify-center gap-1.5 mt-3">
                        <span
                            class="bg-[#EEF8F8] text-[#156F8C] text-[11px] font-medium px-2.5 py-1 rounded-full">Tenant</span>
                        @if($user->hasRole('Landlord'))
                            <span
                                class="bg-green-50 text-green-700 text-[11px] font-medium px-2.5 py-1 rounded-full">Landlord</span>
                        @endif
                    </div>

                    <div class="border-t border-[#E2E8F0] mt-4 pt-4">
                        <div class="grid grid-cols-2 gap-2 text-center">
                            <div>
                                <p class="text-[18px] font-bold text-[#1F2937]">{{ $user->reviews()->count() }}</p>
                                <p class="text-[11px] text-[#64748B] mt-0.5">
                                    {{ Str::plural('Review', $user->reviews()->count()) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[18px] font-bold text-[#1F2937]">{{ $user->favorites()->count() }}</p>
                                <p class="text-[11px] text-[#64748B] mt-0.5">Saved</p>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-[#E2E8F0] mt-4 pt-3">
                        <p class="text-[11px] text-[#64748B] flex items-center justify-center gap-1.5">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                            </svg>
                            Member since {{ $user->created_at->format('F Y') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Right: edit form --}}
            <div>

                @if($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-[13px] font-medium mb-6">
                        @foreach($errors->all() as $error)
                            <p class="mb-0">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form action="{{ route('tenant.profile.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')

                    {{-- Avatar section --}}
                    <div class="bg-white/70 backdrop-blur-xl rounded-2xl border border-white/30 shadow-lg p-5 mb-4">
                        <p class="text-[13px] font-semibold text-[#64748B] mb-3">Profile picture</p>
                        <div class="flex items-center gap-4">
                            <div id="avatar-preview-wrapper">
                                @if($user->profile_picture)
                                    <img id="avatar-preview" src="{{ $user->profile_picture }}" alt="{{ $user->first_name }}"
                                        class="w-16 h-16 rounded-full object-cover">
                                @else
                                    <div id="avatar-preview"
                                        class="w-16 h-16 rounded-full bg-[#2AA7A1] flex items-center justify-center text-white text-[22px] font-bold">
                                        {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
                                    </div>
                                @endif
                            </div>
                            <div>
                                <label for="profile_picture"
                                    class="inline-flex items-center gap-2 px-4 py-2 border border-[#E2E8F0] rounded-lg text-[13px] font-semibold text-[#1F2937] hover:brightness-95 bg-white transition-all cursor-pointer">
                                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                                    </svg>
                                    Change photo
                                </label>
                                <input type="file" name="profile_picture" id="profile_picture" class="hidden"
                                    accept="image/*">
                                <p class="text-[11px] text-[#64748B] mt-1.5">JPG, PNG. Max 2MB.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Personal info --}}
                    <div class="bg-white/70 backdrop-blur-xl rounded-2xl border border-white/30 shadow-lg p-5 mb-4">
                        <p class="text-[13px] font-semibold text-[#64748B] mb-4">Personal information</p>

                        <div class="grid grid-cols-2 gap-3 mb-3">
                            <div>
                                <label for="first_name" class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">First
                                    name</label>
                                <input type="text" name="first_name" id="first_name"
                                    value="{{ old('first_name', $user->first_name) }}" required
                                    class="w-full px-4 py-2.5 bg-white border border-[#E2E8F0] rounded-xl text-[14px] text-[#1F2937] focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all">
                            </div>
                            <div>
                                <label for="last_name" class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">Last
                                    name</label>
                                <input type="text" name="last_name" id="last_name"
                                    value="{{ old('last_name', $user->last_name) }}" required
                                    class="w-full px-4 py-2.5 bg-white border border-[#E2E8F0] rounded-xl text-[14px] text-[#1F2937] focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="contact_number"
                                class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">Contact number</label>
                            <input type="text" name="contact_number" id="contact_number"
                                value="{{ old('contact_number', $user->contact_number) }}"
                                class="w-full px-4 py-2.5 bg-white border border-[#E2E8F0] rounded-xl text-[14px] text-[#1F2937] focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all"
                                placeholder="09171234567">
                        </div>

                        <div>
                            <label for="bio" class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">About me</label>
                            <textarea name="bio" id="bio" rows="4" maxlength="1000"
                                class="w-full px-4 py-2.5 bg-white border border-[#E2E8F0] rounded-xl text-[14px] text-[#1F2937] focus:border-[#2AA7A1] focus:ring-2 focus:ring-[#2AA7A1]/20 focus:outline-none transition-all resize-none"
                                placeholder="Tell landlords a bit about yourself...">{{ old('bio', $user->bio) }}</textarea>
                            <p class="text-[11px] text-[#64748B] mt-1.5 text-right"><span
                                    id="bio-count">{{ strlen(old('bio', $user->bio ?? '')) }}</span>/1000</p>
                        </div>
                    </div>

                    {{-- Account (read-only email) --}}
                    <div class="bg-white/70 backdrop-blur-xl rounded-2xl border border-white/30 shadow-lg p-5 mb-5">
                        <p class="text-[13px] font-semibold text-[#64748B] mb-4">Account</p>
                        <div>
                            <label class="block text-[13px] font-semibold text-[#1F2937] mb-1.5">Email address</label>
                            <input type="email" value="{{ $user->email }}" disabled
                                class="w-full px-4 py-2.5 bg-[#F7FCFC] border border-[#E2E8F0] rounded-xl text-[14px] text-[#64748B] cursor-not-allowed">
                            <p class="text-[11px] text-[#64748B] mt-1.5">Email can be changed in <a
                                    href="{{ route('profile.edit') }}"
                                    class="text-[#156F8C] font-semibold hover:underline">Settings</a>.</p>
                        </div>
                    </div>

                    {{-- Submit --}}
                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('tenant.profile.show') }}"
                            class="px-5 py-2.5 border border-[#E2E8F0] rounded-lg text-[13px] font-semibold text-[#1F2937] hover:brightness-95 bg-white transition-all">Cancel</a>
                        <button type="submit"
                            class="px-6 py-2.5 bg-[#2AA7A1] text-white rounded-lg text-[13px] font-semibold hover:brightness-95 transition-all shadow-sm">Save
                            changes</button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    @push('scripts')
        <script>
            document.getElementById('profile_picture')?.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = function (ev) {
                    const wrapper = document.getElementById('avatar-preview-wrapper');
                    wrapper.innerHTML = '<img id="avatar-preview" src="' + ev.target.result + '" alt="Preview" class="w-16 h-16 rounded-full object-cover">';
                };
                reader.readAsDataURL(file);
            });

            document.getElementById('bio')?.addEventListener('input', function () {
                document.getElementById('bio-count').textContent = this.value.length;
            });
        </script>
    @endpush
@endsection