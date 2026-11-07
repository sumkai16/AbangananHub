@extends(
    auth()->user()->hasRole('Admin') ? 'layouts.admin' : (auth()->user()->usesLandlordShell() ? 'layouts.landlord' : 'layouts.app'),
    auth()->user()->hasRole('Admin') || auth()->user()->usesLandlordShell() ? [] : ['searchBar' => false]
)
@section('title', 'Account Settings')
@section('page-title', 'Account Settings')
@section('content')

    @php
        // Landlords keep only their private account here. Their public face is the business page, edited on My Profile;
        // Payments holds the payment methods and the GCash payout form (shared with Payments > Payouts). Bio, profile strength and the trust tip are about a tenant's own profile.
        $isLandlord = $user->usesLandlordShell();
        $role = $user->hasRole('Landlord') ? 'Landlord' : ($user->hasRole('Tenant') ? 'Tenant' : ($user->hasRole('Admin') ? 'Admin' : 'Member'));

        // Profile strength: four things a person can actually fill in, 25% each. Email is not
        // counted: User does not implement MustVerifyEmail and there is no working verify flow,
        // so that check used to pass for everyone (and lied next to "Email status: Not verified").
        $checks = [
            'a profile photo' => !empty($user->profile_picture),
            'your name' => !empty($user->first_name) && !empty($user->last_name),
            'a contact number' => !empty($user->contact_number),
            'a short bio' => !empty($user->bio),
        ];
        $strength = (int) round((count(array_filter($checks)) / count($checks)) * 100);
        $missing = array_keys(array_filter($checks, fn ($done) => ! $done));
    @endphp

    <div class="{{ auth()->user()->shellContainerClass(auth()->user()->hasRole('Admin')) }} mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-10 min-h-[calc(100vh-72px)]">

        {{-- Back to profile: tenants reach Settings from their profile page. A landlord has Settings as its own
             sidebar item next to My Profile, so there is nothing to go back to. --}}
        @unless($isLandlord)
        <x-back-link href="{{ route('profile.show') }}" to="profile" class="mb-3" />
        @endunless

        {{-- ID-card hero --}}
        <div class="relative overflow-hidden rounded-2xl sm:rounded-[28px] bg-[#060D26] p-4 sm:p-8 mb-4 sm:mb-8 shadow-[0_12px_36px_rgba(21,111,140,0.28)]"
            data-animate>
            {{-- Security-paper dot texture --}}
            <div class="absolute inset-0 opacity-[0.08] pointer-events-none"
                style="background-image: radial-gradient(circle at 1px 1px, white 1.3px, transparent 0); background-size: 22px 22px;"></div>

            <div class="relative flex flex-wrap md:flex-nowrap items-center gap-x-4 gap-y-3 md:gap-8">

                {{-- Avatar --}}
                <div class="flex-shrink-0 relative">
                    @if($user->profile_picture)
                        <img loading="lazy" decoding="async" src="{{ $user->profile_picture }}" alt="{{ $user->first_name }}"
                            class="w-14 h-14 sm:w-24 sm:h-24 rounded-2xl object-cover ring-2 sm:ring-4 ring-white/20">
                    @else
                        <div class="w-14 h-14 sm:w-24 sm:h-24 rounded-2xl bg-white/15 ring-2 sm:ring-4 ring-white/20 flex items-center justify-center text-white text-xl sm:text-[28px] font-black">
                            {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
                        </div>
                    @endif
                    <button type="button" id="avatar-pick" title="Change profile photo" aria-label="Change profile photo"
                        class="absolute -bottom-2 -right-2 w-7 h-7 rounded-full bg-white flex items-center justify-center shadow-md hover:scale-110 transition-transform disabled:opacity-60 disabled:hover:scale-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66]">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                        </svg>
                    </button>
                    {{-- Picking a file uploads it straight away; no trip to another page. --}}
                    <form id="avatar-form" method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data" class="hidden">
                        @csrf
                        <input type="file" id="avatar-input" name="photo" accept="image/jpeg,image/png,image/webp" tabindex="-1">
                    </form>

                </div>

                {{-- Identity --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1.5">
                        <span class="inline-flex items-center gap-1 bg-white/15 text-white text-[11px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-full">
                            {{ $role }}
                        </span>
                        @if($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && $user->hasVerifiedEmail())
                            <span class="inline-flex items-center gap-1 bg-white text-[#060D26] text-[11px] font-bold px-2.5 py-1 rounded-full">
                                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Verified
                            </span>
                        @endif
                    </div>
                    <h1 class="text-[19px] sm:text-[28px] font-normal tracking-tight text-white leading-tight truncate">
                        {{ $user->first_name }} {{ $user->last_name }}
                    </h1>
                    <p class="text-[13.5px] text-white/80 mt-1 truncate">{{ $user->email }}</p>
                    <p class="text-[12px] text-white/60 mt-2 hidden sm:flex items-center gap-1.5">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        Member since {{ $user->created_at->format('F Y') }}
                    </p>
                    @error('photo')
                        <p class="mt-2 text-[12px] font-medium text-[#FCA5A5]" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Profile strength meter --}}
                @unless($isLandlord)
                <div class="w-full md:w-[190px] flex-shrink-0 bg-white/10 rounded-xl sm:rounded-2xl p-3 sm:p-4 backdrop-blur-sm">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-white/70">Profile strength</span>
                        <span class="text-[13px] font-black text-white">{{ $strength }}%</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-white/15 overflow-hidden">
                        <div class="h-full rounded-full bg-white transition-all duration-700 ease-out" style="width: {{ $strength }}%"></div>
                    </div>
                    <p class="text-[10.5px] text-white/60 mt-2 leading-snug max-sm:line-clamp-1">
                        @if($strength >= 100)
                            Your account is fully set up.
                        @else
                            Add {{ implode(', ', array_slice($missing, 0, -1)) }}{{ count($missing) > 1 ? ' and ' : '' }}{{ end($missing) }} to build trust with the community.
                        @endif
                    </p>
                </div>
                @endunless
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:gap-8 lg:grid-cols-[210px_1fr]">

            {{-- Side column (desktop only): account facts. The collapsible panels are the section menu, so there is no
                 separate list of section links any more. --}}
            <aside class="max-lg:hidden lg:sticky lg:top-24 h-fit flex flex-col gap-4">
                {{-- Account overview --}}
                <div class="max-lg:hidden bg-white rounded-2xl ring-1 ring-[#5B6A8E]/10 shadow-[0_2px_12px_rgba(6,13,38,0.05)] p-5">
                    <h3 class="text-[11px] font-normal uppercase tracking-widest text-[#5B6A8E] mb-3.5">Account overview</h3>
                    <dl class="flex flex-col gap-3.5">
                        @unless($isLandlord)
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-[#ECEEF6] flex items-center justify-center shrink-0">
                                @if($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && $user->hasVerifiedEmail())
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                @else
                                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="#D97706" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <dt class="text-[11px] text-[#5B6A8E] leading-tight">Email status</dt>
                                <dd class="text-[13px] font-semibold text-[#060D26] leading-tight mt-0.5">
                                    {{ $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && $user->hasVerifiedEmail() ? 'Verified' : 'Not verified' }}
                                </dd>
                            </div>
                        </div>
                        @endunless
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-[#ECEEF6] flex items-center justify-center shrink-0">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-[11px] text-[#5B6A8E] leading-tight">Member since</dt>
                                <dd class="text-[13px] font-semibold text-[#060D26] leading-tight mt-0.5">{{ $user->created_at->format('M d, Y') }}</dd>
                            </div>
                        </div>
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-[#ECEEF6] flex items-center justify-center shrink-0">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-[11px] text-[#5B6A8E] leading-tight">Last updated</dt>
                                <dd class="text-[13px] font-semibold text-[#060D26] leading-tight mt-0.5">{{ $user->updated_at->diffForHumans() }}</dd>
                            </div>
                        </div>
                    </dl>
                </div>

                {{-- Trust tip --}}
                @unless($isLandlord)
                <div class="max-lg:hidden rounded-2xl bg-[#ECEEF6] p-5">
                    <div class="w-8 h-8 rounded-lg bg-white flex items-center justify-center mb-3 shadow-sm">
                        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253" />
                        </svg>
                    </div>
                    <p class="text-[12.5px] font-semibold text-[#060D26] leading-snug">A complete, verified profile builds trust</p>
                    <p class="text-[11.5px] text-[#060D26]/80 leading-relaxed mt-1.5">
                        Landlords and tenants are more likely to respond to accounts with a photo, bio, and verified email.
                    </p>
                </div>
                @endunless
            </aside>

            {{-- Main content --}}
            {{-- Each section is a collapsible panel (x-settings-panel): closed, the page is a short list with a one-line
                 summary per section; tapping a header (or its rail link) opens the form. A section whose form came back
                 with errors opens on its own, and a redirect to #payments opens Payments. --}}
            @php
                $icon = fn (string $d, string $stroke = '#060D26') => '<svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="'.$stroke.'" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="'.$d.'" /></svg>';
                $personalSummary = trim($user->first_name.' '.$user->last_name).' · '.$user->email;
                if ($isLandlord) {
                    $ways = array_keys(array_filter([
                        'Online' => \App\Support\Payments::online() && $user->acceptsOnlinePayments(),
                        'QR' => $user->accepts_qr_payments ?? true,
                        'Cash' => $user->accepts_cash_payments ?? true,
                    ]));
                    $waysText = $ways ? implode(', ', $ways) : 'None';
                    $gcashText = $user->showsPayoutTools()
                        ? ($user->hasPayoutDestination() ? 'GCash ••••'.substr((string) $user->payoutAccount->gcash_number, -4) : 'No GCash yet')
                        : null;
                    $paymentsSummary = $waysText.($gcashText ? ' · '.$gcashText : '');
                }
                $detail = $user->hasRole('Tenant') ? $user->tenantDetail : null;
            @endphp
            <div class="flex flex-col gap-3 sm:gap-4">
                <div data-animate>
                    <x-settings-panel id="personal-info" title="Personal info" :subtitle="$personalSummary"
                        :open="$errors->hasAny(['first_name', 'last_name', 'email', 'contact_number', 'bio', 'profile_visibility'])">
                        <x-slot:icon>{!! $icon('M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z') !!}</x-slot:icon>
                        @include('profile.partials.update-profile-information-form', ['inPanel' => true])
                    </x-settings-panel>
                </div>
                @if($isLandlord)
                    {{-- How tenants can pay, then where online money is paid out (the same GCash form as Payments > Payouts). --}}
                    <div data-animate>
                        <x-settings-panel id="payments" title="Payments" :subtitle="$paymentsSummary"
                            :open="$errors->hasAny(['payment_methods', 'accepts_online', 'accepts_qr', 'accepts_cash', 'gcash_number', 'gcash_account_name', 'current_password'])">
                            <x-slot:icon>{!! $icon('M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z') !!}</x-slot:icon>
                            @include('profile.partials.landlord-payment-methods', ['inPanel' => true])
                            @if($user->showsPayoutTools())
                                @include('landlord.payouts.partials._account', ['returnTo' => 'settings'])
                            @endif
                        </x-settings-panel>
                    </div>
                @endif
                <div data-animate>
                    <x-settings-panel id="security" title="Password" subtitle="Change your password"
                        :open="$errors->updatePassword->isNotEmpty()">
                        <x-slot:icon>{!! $icon('M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z') !!}</x-slot:icon>
                        @include('profile.partials.update-password-form', ['inPanel' => true])
                    </x-settings-panel>
                </div>
                @if($user->hasRole('Tenant'))
                    <div data-animate>
                        <x-settings-panel id="emergency-contact" title="Emergency contact & ID"
                            :subtitle="$detail?->emergency_contact_name ? $detail->emergency_contact_name.' · '.($detail->id_type ?? 'No ID yet') : 'Your landlord keeps these on file'"
                            :open="$errors->hasAny(['emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_number', 'id_type', 'id_number'])">
                            <x-slot:icon>{!! $icon('M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z') !!}</x-slot:icon>
                            @include('profile.partials.update-tenant-details-form', ['inPanel' => true])
                        </x-settings-panel>
                    </div>
                @endif
                <div data-animate>
                    <x-settings-panel id="danger-zone" title="Danger zone" subtitle="Delete your account" tone="danger"
                        :open="$errors->userDeletion->isNotEmpty()">
                        <x-slot:icon>{!! $icon('M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16', '#DC2626') !!}</x-slot:icon>
                        @include('profile.partials.delete-user-form', ['inPanel' => true])
                    </x-settings-panel>
                </div>
            </div>
        </div>
    </div>

    <style>
        @media (prefers-reduced-motion: no-preference) {
            [data-animate] {
                animation: settings-fade-up 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
            }
            @keyframes settings-fade-up {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
        }
    </style>

    @push('scripts')
        <script>
            (function () {
                const pick = document.getElementById('avatar-pick');
                const input = document.getElementById('avatar-input');
                if (!pick || !input) return;
                pick.addEventListener('click', () => input.click());
                input.addEventListener('change', () => {
                    if (!input.files.length) return;
                    pick.disabled = true;
                    input.form.submit();
                });
            })();
        </script>
    @endpush
@endsection
