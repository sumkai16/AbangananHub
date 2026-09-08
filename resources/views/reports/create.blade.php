@extends(auth()->check() && auth()->user()->hasRole('Landlord') ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])

@section('content')
    <div class="min-h-[calc(100vh-72px)] py-10 px-4 sm:px-6 lg:px-8">
       <div class="max-w-5xl mx-auto">

            {{-- Header --}}
            <div class="mb-8">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-xl bg-[#060D26] flex items-center justify-center shrink-0">
                        @if($targetType === 'user')
                            <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                        @else
                            <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                            </svg>
                        @endif
                    </div>
                    <div>
                        <h1 class="text-2xl font-normal text-[#060D26] leading-tight">
                            {{ $targetType === 'user' ? 'Report a user' : ($targetType === 'property' ? 'Report a listing' : 'Report a problem') }}
                        </h1>
                        <p class="mt-1 text-sm text-[#5B6A8E]">
                            {{ $targetType === 'user' ? 'Let us know what this user did wrong.' : ($targetType === 'property' ? 'Let us know what\'s wrong with this listing.' : 'Let us know about a listing or user that violates our community guidelines.') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Errors --}}
            @if($errors->any())
                <div class="mb-6 px-4 py-3 rounded-xl bg-[#EF4444]/[0.07] border border-[#EF4444]/20 text-[#DC2626] text-sm font-medium">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="grid lg:grid-cols-[1fr_280px] gap-6 items-start">

                {{-- Form --}}
                <form method="POST" action="{{ route('reports.store') }}"
                    class="bg-white rounded-2xl border border-[#E2E4EC] shadow-[0_1px_3px_rgba(6,13,38,0.06)] p-6 sm:p-8 space-y-6"
                    x-data="{ targetType: '{{ old('target_type', $targetType ?? 'property') }}' }">
                    @csrf

                    @if($targetType)
                        {{-- Locked target card --}}
                        <input type="hidden" name="target_type" value="{{ $targetType }}">

                        @if($targetType === 'property' && $targetProperty)
                            <input type="hidden" name="property_id" value="{{ $targetProperty->property_id }}">
                            <div class="flex items-center gap-3 p-4 rounded-xl bg-[#F7F8FC] ring-1 ring-[#5B6A8E]/15">
                                <div class="w-10 h-10 rounded-lg bg-[#ECEEF6] flex items-center justify-center shrink-0">
                                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[13.5px] font-semibold text-[#060D26] truncate">{{ $targetProperty->title }}</p>
                                    <p class="text-[11px] text-[#5B6A8E] mt-0.5">by
                                        {{ $targetProperty->landlord->first_name ?? '' }}
                                        {{ $targetProperty->landlord->last_name ?? '' }}</p>
                                </div>
                            </div>
                        @elseif($targetType === 'user' && $targetUser)
                            <input type="hidden" name="reported_user_id" value="{{ $targetUser->user_id }}">
                            <div class="flex items-center gap-3 p-4 rounded-xl bg-[#F7F8FC] ring-1 ring-[#5B6A8E]/15">
                                <div
                                    class="w-10 h-10 rounded-full bg-[#060D26] flex items-center justify-center shrink-0 text-white text-[13px] font-bold">
                                    {{ strtoupper(substr($targetUser->first_name, 0, 1)) }}{{ strtoupper(substr($targetUser->last_name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="text-[13.5px] font-semibold text-[#060D26]">{{ $targetUser->first_name }}
                                        {{ $targetUser->last_name }}</p>
                                    <p class="text-[11px] text-[#5B6A8E] mt-0.5">{{ $targetUser->email }}</p>
                                </div>
                            </div>
                        @endif
                    @else
                        {{-- Fallback: no pre-selected target — show picker --}}
                        <div>
                            <label class="block text-[12px] font-semibold text-[#060D26] mb-2">
                                What are you reporting? <span class="text-[#EF4444]">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <label
                                    class="relative cursor-pointer rounded-xl border-2 px-4 py-4 transition-colors duration-150"
                                    :class="targetType === 'property' ? 'border-[#C9A84C] bg-[#ECEEF6]' : 'border-[#5B6A8E]/20 bg-white hover:border-[#5B6A8E]/40'">
                                    <input type="radio" name="target_type" value="property" x-model="targetType"
                                        class="sr-only">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center mb-2"
                                        :class="targetType === 'property' ? 'bg-[#060D26]' : 'bg-[#F7F8FC]'">
                                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                            :stroke="targetType === 'property' ? 'white' : '#5B6A8E'">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75" />
                                        </svg>
                                    </div>
                                    <p class="text-[13.5px] font-semibold text-[#060D26]">A Property Listing</p>
                                    <p class="text-[11px] text-[#5B6A8E] mt-0.5">Scam, fake, or inappropriate listing</p>
                                </label>
                                <label
                                    class="relative cursor-pointer rounded-xl border-2 px-4 py-4 transition-colors duration-150"
                                    :class="targetType === 'user' ? 'border-[#C9A84C] bg-[#ECEEF6]' : 'border-[#5B6A8E]/20 bg-white hover:border-[#5B6A8E]/40'">
                                    <input type="radio" name="target_type" value="user" x-model="targetType" class="sr-only">
                                    <div class="w-9 h-9 rounded-lg flex items-center justify-center mb-2"
                                        :class="targetType === 'user' ? 'bg-[#060D26]' : 'bg-[#F7F8FC]'">
                                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                            :stroke="targetType === 'user' ? 'white' : '#5B6A8E'">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                        </svg>
                                    </div>
                                    <p class="text-[13.5px] font-semibold text-[#060D26]">A User</p>
                                    <p class="text-[11px] text-[#5B6A8E] mt-0.5">A landlord or tenant</p>
                                </label>
                            </div>
                        </div>

                        {{-- Property picker --}}
                        <div x-show="targetType === 'property'" x-cloak>
                            <label class="block text-[12px] font-semibold text-[#060D26] mb-1.5">
                                Select Property <span class="text-[#EF4444]">*</span>
                            </label>
                            @php
                                $propertyOptions = ['' => 'Select a property...'] + $properties->pluck('title', 'property_id')->all();
                            @endphp
                            <x-styled-select name="property_id" :options="$propertyOptions" :selected="(string) old('property_id', '')"
                                class="h-11 w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 text-[13.5px] text-[#060D26]" />
                        </div>

                        {{-- User picker --}}
                        <div x-show="targetType === 'user'" x-cloak>
                            <label class="block text-[12px] font-semibold text-[#060D26] mb-1.5">
                                Select User <span class="text-[#EF4444]">*</span>
                            </label>
                            @php
                                $reportedUserOptions = ['' => 'Select a user...'] + $users->mapWithKeys(fn ($user) => [
                                    (string) $user->user_id => $user->first_name . ' ' . $user->last_name . ' (' . $user->email . ')',
                                ])->all();
                            @endphp
                            <x-styled-select name="reported_user_id" :options="$reportedUserOptions" :selected="(string) old('reported_user_id', '')"
                                class="h-11 w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 text-[13.5px] text-[#060D26]" />
                        </div>

                        <div class="h-px bg-[#E2E4EC]"></div>
                    @endif

                    {{-- Reason --}}
                    <div>
                        <label class="block text-[12px] font-semibold text-[#060D26] mb-1.5">
                            Reason <span class="text-[#EF4444]">*</span>
                        </label>
                        @php
                            $categoryOptions = ['' => 'Select a reason...'] + array_combine($categories, $categories);
                        @endphp
                        <x-styled-select name="category" :options="$categoryOptions" :selected="old('category', '')"
                            class="h-11 w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 text-[13.5px] text-[#060D26]" />
                    </div>

                    {{-- Details --}}
                    <div>
                        <label class="block text-[12px] font-semibold text-[#060D26] mb-1.5">
                            Additional details
                        </label>
                        <textarea name="details" rows="4" maxlength="1000"
                            placeholder="Tell us more about what happened (optional)"
                            class="w-full rounded-xl border border-[#5B6A8E]/30 px-3.5 py-3 text-[13.5px] text-[#060D26] placeholder-[#5B6A8E] focus:outline-none focus:ring-2 focus:ring-[#C9A84C]/30 transition">{{ old('details') }}</textarea>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit"
                            class="h-11 px-6 inline-flex items-center justify-center rounded-full bg-[#060D26] text-white text-sm font-semibold hover:brightness-95 transition-all duration-200">
                            Submit report
                        </button>
                        <p class="text-[11px] text-[#5B6A8E]">Our team reviews all reports and takes appropriate action.</p>
                    </div>
                </form>

                {{-- Side info panel --}}
                <div class="space-y-4 lg:sticky lg:top-8">
                    <div class="bg-white rounded-2xl border border-[#E2E4EC] shadow-[0_1px_3px_rgba(6,13,38,0.06)] p-5">
                        <h2 class="text-[13px] font-normal text-[#060D26] mb-4">What happens next?</h2>
                        <div class="space-y-4">
                            <div class="flex gap-3">
                                <div
                                    class="w-6 h-6 rounded-full bg-[#ECEEF6] text-[#060D26] text-[11px] font-bold flex items-center justify-center shrink-0">
                                    1</div>
                                <p class="text-[12.5px] text-[#5B6A8E] leading-relaxed">Your report is sent to our
                                    moderation team and marked <span class="font-semibold text-[#060D26]">Pending</span>.
                                </p>
                            </div>
                            <div class="flex gap-3">
                                <div
                                    class="w-6 h-6 rounded-full bg-[#ECEEF6] text-[#060D26] text-[11px] font-bold flex items-center justify-center shrink-0">
                                    2</div>
                                <p class="text-[12.5px] text-[#5B6A8E] leading-relaxed">An admin reviews and investigates
                                    the details you provided.</p>
                            </div>
                            <div class="flex gap-3">
                                <div
                                    class="w-6 h-6 rounded-full bg-[#ECEEF6] text-[#060D26] text-[11px] font-bold flex items-center justify-center shrink-0">
                                    3</div>
                                <p class="text-[12.5px] text-[#5B6A8E] leading-relaxed">Appropriate action is taken and the
                                    report is marked <span class="font-semibold text-[#060D26]">Resolved</span>.</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-[#060D26] rounded-2xl p-5">
                        <div class="flex items-center gap-2 mb-2">
                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#C9A84C" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                            <h2 class="text-[13px] font-normal text-white">Good to know</h2>
                        </div>
                        <p class="text-[12px] text-white/60 leading-relaxed">Reports are confidential — the person or
                            listing you report will not be notified that you submitted it. False reports may affect your
                            account standing.</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection