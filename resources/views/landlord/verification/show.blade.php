@extends('layouts.app', ['searchBar' => false])

@section('content')
    @push('mobile-title')<x-page-icon class="!h-7 !w-7 !rounded-lg" path="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" /><span class="truncate text-[15px] font-semibold tracking-tight text-[#060D26]">Landlord verification</span>@endpush

    @php
        $status = $verification->verification_status;
        $isPending = $status === 'Pending';
        $isApproved = $status === 'Approved';
        $submitted = $verification->submitted_at;
        $reviewed = $verification->reviewed_at;

        // Each step: dot state drives colour. done = coral, now = amber, todo = hollow, approved/rejected = semantic.
        $steps = [
            ['label' => 'Submitted', 'sub' => $submitted->format('M j, g:i A'), 'state' => 'done'],
            ['label' => 'Admin review', 'sub' => $isPending ? 'In progress' : 'Reviewed', 'state' => $isPending ? 'now' : 'done'],
            $isPending
                ? ['label' => 'Decision', 'sub' => 'We will notify you', 'state' => 'todo']
                : ['label' => $isApproved ? 'Approved' : 'Not approved', 'sub' => $reviewed?->format('M j, g:i A'), 'state' => $isApproved ? 'approved' : 'rejected'],
        ];
        $dot = [
            'done' => 'bg-[#FF8A66] border-[#FF8A66]',
            'now' => 'bg-[#FBBF24] border-[#FBBF24] ring-4 ring-[#FBBF24]/25',
            'todo' => 'bg-white border-[#E2E4EC]',
            'approved' => 'bg-[#22C55E] border-[#22C55E]',
            'rejected' => 'bg-[#EF4444] border-[#EF4444]',
        ];

        $ocr = match ($verification->ocr_status) {
            'pass' => ['Name matched your account', 'bg-[#22C55E]/[0.08]', 'text-[#22C55E]', 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
            'partial' => ['Partial name match. An admin will verify.', 'bg-[#FBBF24]/10', 'text-[#FBBF24]', 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.814-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z'],
            null => null,
            default => ['Name not verified. An admin will check manually.', 'bg-[#EF4444]/[0.07]', 'text-[#EF4444]', 'm9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
        };

        $idSummary = $verification->id_type.($verification->maskedIdNumber() ? ' · '.$verification->maskedIdNumber() : '');
        $photoSummary = $verification->id_back ? 'ID front, ID back, selfie' : 'ID front, selfie · ID back not provided';
        $businessSummary = $verification->business_name
            ? $verification->business_name.($verification->business_address ? ' · '.$verification->business_address : '')
            : 'Not provided';

        $photos = [
            ['ID front', route('verifications.preview', [$verification, 'front'])],
            ['ID back', $verification->id_back ? route('verifications.preview', [$verification, 'back']) : null],
            ['Selfie', route('verifications.preview', [$verification, 'selfie'])],
        ];
    @endphp

    <div class="min-h-[calc(100vh-72px)] py-6 sm:py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-6xl mx-auto" x-data="{ previewImage: null, open: { identity: true, photos: false, business: false } }">

            {{-- Back link: the applicant usually isn't a Landlord yet, so homeRoute() sends them
                 wherever they belong instead of 403'ing on a landlord route. --}}
            <a href="{{ auth()->user()->homeRoute() }}"
               class="inline-flex items-center gap-1.5 text-sm text-[#B35A3D] hover:text-[#060D26] mb-5 transition-colors">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                Back
            </a>

            <div class="flex items-center gap-3.5 mb-5">
                <x-page-icon path="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" class="page-header-title" />
                <div>
                    <h1 class="page-header-title text-xl sm:text-2xl font-normal text-[#060D26]">Landlord verification</h1>
                    <p class="hidden sm:block text-sm text-[#5B6A8E] mt-0.5">Status of your identity verification application.</p>
                </div>
            </div>

            {{-- ═══ Status card: headline + 3-step tracker ═══ --}}
            <x-card class="mb-3 lg:mb-5">
                <div class="flex items-start gap-3">
                    <span class="w-10 h-10 shrink-0 rounded-xl grid place-items-center
                        {{ $isPending ? 'bg-[#FBBF24]/15 text-[#FBBF24]' : ($isApproved ? 'bg-[#22C55E]/15 text-[#22C55E]' : 'bg-[#EF4444]/10 text-[#EF4444]') }}">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            @if ($isPending)
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            @elseif ($isApproved)
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            @else
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            @endif
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="hidden lg:block text-[11px] font-semibold uppercase tracking-wider text-[#5B6A8E]">Application #{{ $verification->verification_id }}</p>
                        <h2 class="text-base lg:text-lg font-normal text-[#060D26]">
                            {{ $isPending ? 'Under review' : ($isApproved ? "You're a verified landlord" : 'Application not approved') }}
                        </h2>
                        <p class="text-sm text-[#5B6A8E] mt-0.5 max-w-prose">
                            @if ($isPending)
                                Submitted {{ $submitted->format('F j, Y') }}. An admin is checking your documents and will notify you once a decision is made.
                            @elseif ($isApproved)
                                Approved on {{ $reviewed->format('F j, Y') }}. You can now create and manage property listings on AbangananHub.
                            @else
                                Reviewed on {{ $reviewed->format('F j, Y') }}.
                            @endif
                        </p>
                    </div>
                </div>

                @if (! $isPending && ! $isApproved && $verification->admin_notes)
                    <div class="mt-4 rounded-lg bg-[#EF4444]/[0.07] border border-[#EF4444]/10 p-3">
                        <p class="text-xs font-semibold text-[#5B6A8E] mb-1">Reason</p>
                        <p class="text-sm text-[#060D26]">{{ $verification->admin_notes }}</p>
                    </div>
                @endif

                {{-- Tracker --}}
                <ol class="grid grid-cols-3 mt-6">
                    @foreach ($steps as $step)
                        <li class="relative pt-6 pr-2">
                            @unless ($loop->last)
                                <span class="absolute top-[5px] left-0 right-0 h-0.5 {{ $step['state'] === 'done' ? 'bg-[#FF8A66]' : 'bg-[#E2E4EC]' }}"></span>
                            @endunless
                            <span class="absolute top-0 left-0 w-3 h-3 rounded-full border-2 {{ $dot[$step['state']] }}"></span>
                            <p class="text-xs sm:text-sm {{ $step['state'] === 'todo' ? 'text-[#5B6A8E]' : 'font-semibold text-[#060D26]' }}">{{ $step['label'] }}</p>
                            @if ($step['sub'])
                                <p class="text-[11px] sm:text-xs text-[#5B6A8E] mt-0.5">{{ $step['sub'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>

                @if ($isApproved)
                    <a href="{{ route('landlord.properties.index') }}"
                       class="inline-flex items-center gap-1.5 mt-5 px-4 py-2 rounded-lg text-sm font-semibold text-[#060D26] bg-[#FF8A66] hover:bg-[#E96F4F] transition-all">
                        Go to your listings
                    </a>
                @elseif (! $isPending)
                    <a href="{{ route('landlord.verification.create') }}"
                       class="inline-flex items-center gap-1.5 mt-5 px-4 py-2 rounded-lg text-sm font-semibold text-[#060D26] bg-[#FF8A66] hover:bg-[#E96F4F] transition-all">
                        Update and resubmit
                    </a>
                @endif
            </x-card>

            {{-- ═══ Submission ═══
                 Phone: accordion rows with a one-line summary. lg+: every section is forced open
                 (lg:!block) and the row turns into a plain heading. DOM order is the phone order
                 (identity, photos, business); lg reorders to identity | business, then photos. --}}
            <div class="flex flex-col gap-3 lg:grid lg:grid-cols-2 lg:gap-5">

                {{-- Identity --}}
                <x-card flush class="lg:order-1">
                    <button type="button" @click="open.identity = !open.identity" :aria-expanded="open.identity"
                            class="w-full flex items-center gap-3 p-4 min-h-[56px] text-left lg:p-6 lg:pb-0 lg:pointer-events-none lg:cursor-default">
                        <span class="w-9 h-9 shrink-0 rounded-[10px] bg-[#ECEEF6] text-[#B35A3D] grid place-items-center lg:hidden">
                            <svg class="w-[18px] h-[18px]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z" /></svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[14.5px] lg:text-base text-[#060D26]">Identity</span>
                            <span class="block truncate text-xs text-[#5B6A8E] lg:hidden">{{ $idSummary }}</span>
                        </span>
                        <svg class="w-[18px] h-[18px] shrink-0 text-[#5B6A8E] transition-transform lg:hidden" :class="open.identity && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                    <div x-show="open.identity" x-cloak class="px-4 pb-5 lg:!block lg:px-6 lg:pb-6 lg:pt-4">
                        {{-- Sensitive values are masked; admins see the full ones. --}}
                        <dl class="grid grid-cols-[100px_minmax(0,1fr)] gap-x-3 gap-y-2.5 text-sm">
                            <dt class="text-xs text-[#5B6A8E] pt-0.5">ID type</dt>
                            <dd class="text-[#060D26]">{{ $verification->id_type }}</dd>

                            <dt class="text-xs text-[#5B6A8E] pt-0.5">Name on ID</dt>
                            <dd class="text-[#060D26] break-words">{{ $verification->ocr_name ?: 'Not read from your ID' }}</dd>

                            <dt class="text-xs text-[#5B6A8E] pt-0.5">ID number</dt>
                            <dd class="text-[#060D26] tabular-nums">{{ $verification->maskedIdNumber() ?? 'Not read from your ID' }}</dd>

                            <dt class="text-xs text-[#5B6A8E] pt-0.5">Face check</dt>
                            <dd class="text-[#060D26]">{{ $verification->liveness_passed ? 'Passed' : 'Not completed. An admin will check your selfie by hand.' }}</dd>
                        </dl>

                        @if ($ocr)
                            <div class="flex items-center gap-2 mt-4 px-3 py-2 rounded-lg {{ $ocr[1] }}">
                                <svg class="w-4 h-4 shrink-0 {{ $ocr[2] }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $ocr[3] }}" /></svg>
                                <span class="text-xs text-[#060D26]">{{ $ocr[0] }}</span>
                            </div>
                        @endif
                    </div>
                </x-card>

                {{-- Photos --}}
                <x-card flush class="lg:order-3 lg:col-span-2">
                    <button type="button" @click="open.photos = !open.photos" :aria-expanded="open.photos"
                            class="w-full flex items-center gap-3 p-4 min-h-[56px] text-left lg:p-6 lg:pb-0 lg:pointer-events-none lg:cursor-default">
                        <span class="w-9 h-9 shrink-0 rounded-[10px] bg-[#ECEEF6] text-[#B35A3D] grid place-items-center lg:hidden">
                            <svg class="w-[18px] h-[18px]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0 0 21.75 19.5v-15A1.5 1.5 0 0 0 20.25 3H3.75a1.5 1.5 0 0 0-1.5 1.5v15A1.5 1.5 0 0 0 3.75 21Zm10.5-11.25h.008v.008h-.008V9.75Z" /></svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[14.5px] lg:text-base text-[#060D26]">Submitted photos</span>
                            <span class="block truncate text-xs text-[#5B6A8E] lg:hidden">{{ $photoSummary }}</span>
                        </span>
                        <svg class="w-[18px] h-[18px] shrink-0 text-[#5B6A8E] transition-transform lg:hidden" :class="open.photos && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                    <div x-show="open.photos" x-cloak class="px-4 pb-5 lg:!block lg:px-6 lg:pb-6 lg:pt-4">
                        <div class="grid grid-cols-3 gap-3">
                            @foreach ($photos as [$label, $url])
                                <div class="text-center">
                                    @if ($url)
                                        <button type="button" @click="previewImage = '{{ $url }}'"
                                                class="block w-full aspect-[4/3] rounded-lg border border-[#E2E4EC] overflow-hidden hover:border-[#FF8A66] transition-colors">
                                            <img src="{{ $url }}" alt="{{ $label }}" loading="lazy" class="w-full h-full object-cover">
                                        </button>
                                    @else
                                        <div class="aspect-[4/3] rounded-lg border border-dashed border-[#E2E4EC] flex items-center justify-center">
                                            <span class="text-xs text-[#5B6A8E]">Not provided</span>
                                        </div>
                                    @endif
                                    <p class="text-xs text-[#5B6A8E] mt-1.5">{{ $label }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </x-card>

                {{-- Business --}}
                <x-card flush class="lg:order-2">
                    <button type="button" @click="open.business = !open.business" :aria-expanded="open.business"
                            class="w-full flex items-center gap-3 p-4 min-h-[56px] text-left lg:p-6 lg:pb-0 lg:pointer-events-none lg:cursor-default">
                        <span class="w-9 h-9 shrink-0 rounded-[10px] bg-[#ECEEF6] text-[#B35A3D] grid place-items-center lg:hidden">
                            <svg class="w-[18px] h-[18px]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614" /></svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[14.5px] lg:text-base text-[#060D26]">Business</span>
                            <span class="block truncate text-xs text-[#5B6A8E] lg:hidden">{{ $businessSummary }}</span>
                        </span>
                        <svg class="w-[18px] h-[18px] shrink-0 text-[#5B6A8E] transition-transform lg:hidden" :class="open.business && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </button>
                    <div x-show="open.business" x-cloak class="px-4 pb-5 lg:!block lg:px-6 lg:pb-6 lg:pt-4">
                        @if ($verification->logo_url)
                            <img src="{{ Storage::disk('public')->url($verification->logo_url) }}" alt="Business logo" loading="lazy"
                                 class="w-14 h-14 rounded-lg border border-[#E2E4EC] object-cover mb-4">
                        @endif
                        <dl class="grid grid-cols-[100px_minmax(0,1fr)] gap-x-3 gap-y-2.5 text-sm">
                            <dt class="text-xs text-[#5B6A8E] pt-0.5">Name</dt>
                            <dd class="text-[#060D26] break-words">{{ $verification->business_name ?: '—' }}</dd>

                            <dt class="text-xs text-[#5B6A8E] pt-0.5">Description</dt>
                            <dd class="text-[#060D26] break-words">{{ $verification->description ?: '—' }}</dd>

                            <dt class="text-xs text-[#5B6A8E] pt-0.5">Contact</dt>
                            <dd class="text-[#060D26] tabular-nums">{{ $verification->maskedContactNumber() ?? '—' }}</dd>

                            <dt class="text-xs text-[#5B6A8E] pt-0.5">Address</dt>
                            <dd class="text-[#060D26] break-words">{{ $verification->business_address ?: '—' }}</dd>
                        </dl>
                    </div>
                </x-card>
            </div>

            {{-- Lightbox --}}
            <div x-show="previewImage"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @click="previewImage = null"
                 @keydown.escape.window="previewImage = null"
                 class="fixed inset-0 z-[200] flex items-center justify-center bg-black/70 p-4 cursor-pointer"
                 style="display: none;">
                <button type="button" @click="previewImage = null" aria-label="Close preview"
                        class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors">
                    <svg class="w-6 h-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
                <img :src="previewImage" alt="Document preview" @click.stop class="max-w-full max-h-[85vh] rounded-lg cursor-default">
            </div>

        </div>
    </div>
@endsection
