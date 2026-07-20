@extends('layouts.app')

@section('hide_search', true)

@section('content')
    <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-14 min-h-[calc(100vh-72px)]" x-data="{ bio: {{ strlen(old('bio', $user->bio ?? '')) }} }">

        {{-- Back link --}}
        <a href="{{ route('tenant.profile.show') }}"
            class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#156F8C] hover:brightness-90 transition-all mb-5">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
            Back to Profile
        </a>

        {{-- Page header --}}
        <div class="flex items-center gap-3.5 mb-6">
            <div class="w-11 h-11 rounded-xl bg-[#1F2937] flex items-center justify-center shrink-0">
                <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-[#1F2937]">Edit profile</h1>
                <p class="text-sm text-[#64748B] mt-0.5">This is how landlords will see you when you inquire or reserve.</p>
            </div>
        </div>

        @if($errors->any())
            <div class="bg-[#EF4444]/[0.07] border border-[#EF4444]/25 text-[#DC2626] rounded-xl px-4 py-3 text-[13px] font-medium mb-6">
                @foreach($errors->all() as $error)
                    <p class="mb-0">{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-[240px_1fr]">

            {{-- Live preview sidebar --}}
            <aside class="lg:sticky lg:top-24 h-fit flex flex-col gap-4">
                <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] p-6 text-center">
                    <div id="sidebar-avatar" class="mx-auto mb-4">
                        @if($user->profile_picture)
                            <img src="{{ $user->profile_picture }}" alt="{{ $user->first_name }}" class="w-20 h-20 rounded-2xl object-cover mx-auto ring-4 ring-[#EEF8F8]">
                        @else
                            <div class="w-20 h-20 rounded-2xl bg-[#2AA7A1] flex items-center justify-center text-white text-[26px] font-black mx-auto ring-4 ring-[#EEF8F8]">
                                {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
                            </div>
                        @endif
                    </div>
                    <p class="text-[15px] font-bold text-[#1F2937]">{{ $user->first_name }} {{ $user->last_name }}</p>
                    <p class="text-[12px] text-[#64748B] mt-1 truncate">{{ $user->email }}</p>

                    <div class="flex justify-center gap-1.5 mt-3">
                        <span class="bg-[#EEF8F8] text-[#156F8C] text-[11px] font-bold px-2.5 py-1 rounded-full">Tenant</span>
                        @if($user->hasRole('Landlord'))
                            <span class="bg-[#22C55E]/[0.07] text-[#15803D] text-[11px] font-bold px-2.5 py-1 rounded-full">Landlord</span>
                        @endif
                    </div>

                    <div class="border-t border-[#E2E8F0] mt-4 pt-4 grid grid-cols-2 gap-2 text-center">
                        <div>
                            <p class="text-[18px] font-extrabold text-[#1F2937]">{{ $user->reviews()->count() }}</p>
                            <p class="text-[11px] text-[#64748B] mt-0.5">{{ Str::plural('Review', $user->reviews()->count()) }}</p>
                        </div>
                        <div>
                            <p class="text-[18px] font-extrabold text-[#1F2937]">{{ $user->favorites()->count() }}</p>
                            <p class="text-[11px] text-[#64748B] mt-0.5">Saved</p>
                        </div>
                    </div>
                </div>

                {{-- Privacy note --}}
                <div class="rounded-2xl bg-[#EEF8F8] p-5">
                    <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center mb-3 shadow-sm">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                    </div>
                    <p class="text-[12.5px] font-semibold text-[#1F2937] leading-snug">Your email stays private</p>
                    <p class="text-[11.5px] text-[#156F8C]/80 leading-relaxed mt-1.5">
                        Only your name, photo, and bio are visible to landlords. Your email is never shown publicly.
                    </p>
                </div>
            </aside>

            {{-- Form --}}
            <div>
                <form action="{{ route('tenant.profile.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')

                    {{-- Photo --}}
                    <div class="rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] bg-white p-6 sm:p-8 mb-5">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-10 h-10 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                                </svg>
                            </div>
                            <h2 class="text-[15px] font-semibold text-[#1F2937]">Profile picture</h2>
                        </div>
                        <div class="flex items-center gap-5">
                            <div id="avatar-preview-wrapper">
                                @if($user->profile_picture)
                                    <img id="avatar-preview" src="{{ $user->profile_picture }}" alt="{{ $user->first_name }}" class="w-16 h-16 rounded-2xl object-cover">
                                @else
                                    <div id="avatar-preview" class="w-16 h-16 rounded-2xl bg-[#2AA7A1] flex items-center justify-center text-white text-[22px] font-bold">
                                        {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
                                    </div>
                                @endif
                            </div>
                            <div>
                                <label for="profile_picture"
                                    class="inline-flex items-center gap-2 px-4 py-2 border border-[#E2E8F0] rounded-lg text-[13px] font-semibold text-[#1F2937] hover:brightness-95 bg-white transition-all cursor-pointer">
                                    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                    </svg>
                                    Change photo
                                </label>
                                <input type="file" name="profile_picture" id="profile_picture" class="hidden" accept="image/*">
                                <p class="text-[11px] text-[#64748B] mt-1.5">JPG, PNG. Max 2MB. Cropped to square.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Personal info --}}
                    <div class="rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] bg-white p-6 sm:p-8 mb-5">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <h2 class="text-[15px] font-semibold text-[#1F2937]">Personal information</h2>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">
                            <div>
                                <label for="first_name" class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-[#64748B]">First name</label>
                                <input type="text" name="first_name" id="first_name" value="{{ old('first_name', $user->first_name) }}" required
                                    class="h-10 w-full rounded-xl border border-[#E2E8F0] bg-[#E2E8F0]/30 px-3.5 text-sm text-[#1F2937] outline-none transition
                                           focus:border-[#2AA7A1] focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/15">
                            </div>
                            <div>
                                <label for="last_name" class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-[#64748B]">Last name</label>
                                <input type="text" name="last_name" id="last_name" value="{{ old('last_name', $user->last_name) }}" required
                                    class="h-10 w-full rounded-xl border border-[#E2E8F0] bg-[#E2E8F0]/30 px-3.5 text-sm text-[#1F2937] outline-none transition
                                           focus:border-[#2AA7A1] focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/15">
                            </div>
                        </div>

                        <div class="mb-5">
                            <label for="contact_number" class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-[#64748B]">Contact number</label>
                            <input type="text" name="contact_number" id="contact_number" value="{{ old('contact_number', $user->contact_number) }}"
                                placeholder="09171234567"
                                class="h-10 w-full rounded-xl border border-[#E2E8F0] bg-[#E2E8F0]/30 px-3.5 text-sm text-[#1F2937] outline-none transition
                                       focus:border-[#2AA7A1] focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/15 placeholder:text-[#64748B]/50">
                        </div>

                        <div>
                            <label for="bio" class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-[#64748B]">About me</label>
                            <textarea name="bio" id="bio" rows="4" maxlength="1000" x-on:input="bio = $event.target.value.length"
                                placeholder="Tell landlords a bit about yourself…"
                                class="w-full rounded-xl border border-[#E2E8F0] bg-[#E2E8F0]/30 px-3.5 py-2.5 text-sm text-[#1F2937] outline-none transition resize-none
                                       focus:border-[#2AA7A1] focus:bg-white focus:ring-2 focus:ring-[#2AA7A1]/15 placeholder:text-[#64748B]/50">{{ old('bio', $user->bio) }}</textarea>
                            <p class="text-[11px] text-[#64748B] mt-1.5 text-right"><span x-text="bio">0</span>/1000</p>
                        </div>
                    </div>

                    {{-- Account (read-only email) --}}
                    <div class="rounded-2xl ring-1 ring-[#64748B]/10 shadow-[0_2px_12px_rgba(15,23,42,0.05)] bg-white p-6 sm:p-8 mb-6">
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-10 h-10 rounded-xl bg-[#EEF8F8] flex items-center justify-center shrink-0">
                                <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="#156F8C" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                            </div>
                            <h2 class="text-[15px] font-semibold text-[#1F2937]">Account</h2>
                        </div>
                        <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-widest text-[#64748B]">Email address</label>
                        <input type="email" id="email" value="{{ $user->email }}" disabled
                            class="h-10 w-full rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] px-3.5 text-sm text-[#64748B] cursor-not-allowed">
                        <p class="text-[11px] text-[#64748B] mt-1.5">Email can be changed in <a href="{{ route('profile.edit') }}" class="text-[#156F8C] font-semibold hover:underline">Account Settings</a>.</p>
                    </div>

                    {{-- Submit --}}
                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('tenant.profile.show') }}"
                            class="px-5 py-2.5 border border-[#E2E8F0] rounded-lg text-[13px] font-semibold text-[#1F2937] hover:brightness-95 bg-white transition-all">Cancel</a>
                        <button type="submit"
                            class="px-6 py-2.5 bg-[#2AA7A1] text-white rounded-lg text-[13px] font-semibold hover:brightness-95 transition-all shadow-sm active:scale-[0.98]">Save changes</button>
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
                    wrapper.innerHTML = '<img id="avatar-preview" src="' + ev.target.result + '" alt="Preview" class="w-16 h-16 rounded-2xl object-cover">';
                };
                reader.readAsDataURL(file);
            });
        </script>
    @endpush
@endsection
