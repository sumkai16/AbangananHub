@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="min-h-screen bg-[#F7FCFC] py-10 px-4 sm:px-6 lg:px-8">
        <div class="max-w-5xl mx-auto">

            {{-- Header --}}
            <div class="mb-8">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-red-50 flex items-center justify-center shrink-0">
                        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#EF4444" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-[#1F2937]">Report a Problem</h1>
                        <p class="mt-1 text-sm text-[#64748B]">Let us know about a listing or user that violates our
                            community guidelines.</p>
                    </div>
                </div>
            </div>

            {{-- Flash / errors --}}
            @if(session('success'))
                <div class="mb-6 px-4 py-3 rounded-xl bg-[#EEF8F8] text-[#1F2937] text-sm font-medium flex items-center gap-2">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        class="shrink-0 text-[#2AA7A1]">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
                    </svg>
                    {{ session('success') }}
                </div>
            @endif
            @if($errors->any())
                <div class="mb-6 px-4 py-3 rounded-xl bg-red-50 border border-red-100 text-red-700 text-sm font-medium">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="grid lg:grid-cols-[1fr_320px] gap-6 items-start">

                {{-- Form --}}
                <form method="POST" action="{{ route('reports.store') }}"
                    class="bg-white rounded-2xl ring-1 ring-[#64748B]/15 p-6 sm:p-8 space-y-6"
                    x-data="{
                        targetType: '{{ old('target_type', $prefillPropertyId ? 'property' : ($prefillUserId ? 'user' : 'property')) }}'
                    }">
                    @csrf

                    {{-- What are you reporting? --}}
                    <div>
                        <label class="block text-[12px] font-semibold text-[#1F2937] mb-2">
                            <span class="inline-flex items-center justify-center w-4.5 h-4.5 rounded-full bg-[#EEF8F8] text-[#156F8C] text-[10px] font-bold mr-1">1</span>
                            What are you reporting? <span class="text-[#EF4444]">*</span>
                        </label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="relative cursor-pointer rounded-xl border-2 px-4 py-4 transition-colors duration-150"
                                :class="targetType === 'property' ? 'border-[#2AA7A1] bg-[#EEF8F8]' : 'border-[#64748B]/20 bg-white hover:border-[#64748B]/40'">
                                <input type="radio" name="target_type" value="property" x-model="targetType" class="sr-only">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center mb-2"
                                    :class="targetType === 'property' ? 'bg-[#2AA7A1]' : 'bg-[#F7FCFC]'">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                        :stroke="targetType === 'property' ? 'white' : '#64748B'">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                                    </svg>
                                </div>
                                <p class="text-[13.5px] font-semibold text-[#1F2937]">A Property Listing</p>
                                <p class="text-[11px] text-[#64748B] mt-0.5">Scam, fake, or inappropriate listing</p>
                            </label>
                            <label class="relative cursor-pointer rounded-xl border-2 px-4 py-4 transition-colors duration-150"
                                :class="targetType === 'user' ? 'border-[#2AA7A1] bg-[#EEF8F8]' : 'border-[#64748B]/20 bg-white hover:border-[#64748B]/40'">
                                <input type="radio" name="target_type" value="user" x-model="targetType" class="sr-only">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center mb-2"
                                    :class="targetType === 'user' ? 'bg-[#2AA7A1]' : 'bg-[#F7FCFC]'">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                        :stroke="targetType === 'user' ? 'white' : '#64748B'">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                    </svg>
                                </div>
                                <p class="text-[13.5px] font-semibold text-[#1F2937]">A User</p>
                                <p class="text-[11px] text-[#64748B] mt-0.5">A landlord or tenant</p>
                            </label>
                        </div>
                    </div>

                    {{-- Property picker --}}
                    <div x-show="targetType === 'property'" x-cloak>
                        <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                            <span class="inline-flex items-center justify-center w-4.5 h-4.5 rounded-full bg-[#EEF8F8] text-[#156F8C] text-[10px] font-bold mr-1">2</span>
                            Select Property <span class="text-[#EF4444]">*</span>
                        </label>
                        <select name="property_id"
                            class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                            <option value="">Select a property...</option>
                            @foreach($properties as $property)
                                <option value="{{ $property->property_id }}"
                                    @selected(old('property_id', $prefillPropertyId) == $property->property_id)>
                                    {{ $property->title }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- User picker --}}
                    <div x-show="targetType === 'user'" x-cloak>
                        <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                            <span class="inline-flex items-center justify-center w-4.5 h-4.5 rounded-full bg-[#EEF8F8] text-[#156F8C] text-[10px] font-bold mr-1">2</span>
                            Select User <span class="text-[#EF4444]">*</span>
                        </label>
                        <select name="reported_user_id"
                            class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                            <option value="">Select a user...</option>
                            @foreach($users as $user)
                                <option value="{{ $user->user_id }}"
                                    @selected(old('reported_user_id', $prefillUserId) == $user->user_id)>
                                    {{ $user->name }} ({{ $user->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="h-px bg-[#E2E8F0]"></div>

                    {{-- Category --}}
                    <div>
                        <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                            <span class="inline-flex items-center justify-center w-4.5 h-4.5 rounded-full bg-[#EEF8F8] text-[#156F8C] text-[10px] font-bold mr-1">3</span>
                            Reason <span class="text-[#EF4444]">*</span>
                        </label>
                        <select name="category"
                            class="h-11 w-full rounded-xl border border-[#64748B]/30 px-3.5 text-[13.5px] text-[#1F2937] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">
                            <option value="">Select a reason...</option>
                            @foreach($categories as $category)
                                <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Details --}}
                    <div>
                        <label class="block text-[12px] font-semibold text-[#1F2937] mb-1.5">
                            Additional Details
                        </label>
                        <textarea name="details" rows="5" maxlength="1000"
                            placeholder="Tell us more about what happened (optional)"
                            class="w-full rounded-xl border border-[#64748B]/30 px-3.5 py-3 text-[13.5px] text-[#1F2937] placeholder-[#64748B] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/30 transition">{{ old('details') }}</textarea>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit"
                            class="h-11 px-6 inline-flex items-center justify-center rounded-full bg-[#1F2937] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200">
                            Submit Report
                        </button>
                        <p class="text-[11px] text-[#64748B]">Our team reviews all reports and takes appropriate action.</p>
                    </div>
                </form>

                {{-- Side info panel --}}
                <div class="space-y-4 lg:sticky lg:top-8">
                    <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/15 p-5">
                        <h2 class="text-[13px] font-bold text-[#1F2937] mb-4">What happens next?</h2>
                        <div class="space-y-4">
                            <div class="flex gap-3">
                                <div class="w-6 h-6 rounded-full bg-[#EEF8F8] text-[#156F8C] text-[11px] font-bold flex items-center justify-center shrink-0">1</div>
                                <p class="text-[12.5px] text-[#64748B] leading-relaxed">Your report is sent to our
                                    moderation team and marked <span class="font-semibold text-[#1F2937]">Pending</span>.
                                </p>
                            </div>
                            <div class="flex gap-3">
                                <div class="w-6 h-6 rounded-full bg-[#EEF8F8] text-[#156F8C] text-[11px] font-bold flex items-center justify-center shrink-0">2</div>
                                <p class="text-[12.5px] text-[#64748B] leading-relaxed">An admin reviews the listing or
                                    user account and investigates the details you provided.</p>
                            </div>
                            <div class="flex gap-3">
                                <div class="w-6 h-6 rounded-full bg-[#EEF8F8] text-[#156F8C] text-[11px] font-bold flex items-center justify-center shrink-0">3</div>
                                <p class="text-[12.5px] text-[#64748B] leading-relaxed">Appropriate action is taken and
                                    the report is marked <span class="font-semibold text-[#1F2937]">Resolved</span>.</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-[#0F172A] rounded-2xl p-5">
                        <div class="flex items-center gap-2 mb-2">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#69D2C6" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                            <h2 class="text-[13px] font-bold text-white">Good to know</h2>
                        </div>
                        <p class="text-[12px] text-white/60 leading-relaxed">Reports are confidential — the person or
                            listing you report will not be notified that you submitted it. False reports may affect
                            your account standing.</p>
                    </div>

                    <div class="bg-white rounded-2xl ring-1 ring-[#64748B]/15 p-5">
                        <h2 class="text-[13px] font-bold text-[#1F2937] mb-3">Common reasons to report</h2>
                        <ul class="space-y-2.5">
                            <li class="flex items-start gap-2.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#EF4444] mt-1.5 shrink-0"></span>
                                <p class="text-[12.5px] text-[#64748B] leading-snug">Listing photos or details don't
                                    match what's actually being offered.</p>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mt-1.5 shrink-0"></span>
                                <p class="text-[12.5px] text-[#64748B] leading-snug">Being asked to pay or transfer
                                    money outside the platform.</p>
                            </li>
                            <li class="flex items-start gap-2.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#2AA7A1] mt-1.5 shrink-0"></span>
                                <p class="text-[12.5px] text-[#64748B] leading-snug">Rude, threatening, or harassing
                                    messages from a landlord or tenant.</p>
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-2xl ring-1 ring-[#2AA7A1]/20 bg-[#EEF8F8] p-5 text-center">
                        <p class="text-[12.5px] text-[#1F2937]/70 leading-relaxed mb-3">Need to talk to someone
                            directly instead?</p>
                        <a href="{{ route('conversations.recentMessages') }}"
                            class="inline-flex items-center gap-2 h-9 px-4 rounded-full bg-white ring-1 ring-[#2AA7A1]/30 text-[#156F8C] text-[12.5px] font-semibold hover:bg-[#2AA7A1] hover:text-white hover:ring-[#2AA7A1] transition-colors duration-200">
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                            </svg>
                            Open Messages
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
