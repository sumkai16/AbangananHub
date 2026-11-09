@extends('layouts.admin')

@section('page-title', 'Users')

@section('content')
@php
    $total = $statusCounts['active'] + $statusCounts['suspended'] + $statusCounts['inactive'];
    $link = fn (array $q) => route('admin.users.index', array_filter(array_merge(['search' => $search], $q), fn ($v) => $v !== null && $v !== '' && $v !== 'all' && $v !== 'All'));
@endphp
<div class="max-w-[1600px] mx-auto" x-data="{ userFormOpen: @js(old('_form') === 'create-user') }">

    @include('admin.users._form-modal')

    <x-page-header icon-path="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0112.226-2.517M15 19.128c-.132-.036-.264-.075-.396-.117M15 15.75a4.125 4.125 0 100-8.25 4.125 4.125 0 000 8.25zM4.5 7.5a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0z" :title="['Landlord' => 'Landlords', 'Tenant' => 'Tenants'][$role] ?? 'Users'" subtitle="Everyone who can sign in, and what needs your attention.">
        <x-slot:actions>
            <button type="button" @click="userFormOpen = true" aria-haspopup="dialog"
                class="group inline-flex h-10 items-center gap-2 rounded-lg bg-[#FF8A66] pl-3 pr-4 text-[13.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#E96F4F] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#FF8A66] focus-visible:ring-offset-2 cursor-pointer">
                <span class="flex h-5 w-5 items-center justify-center rounded bg-[#060D26]/10 transition-colors duration-200 group-hover:bg-[#060D26]/15">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" /></svg>
                </span>
                Create user
            </button>
        </x-slot:actions>
    </x-page-header>

    {{-- The people, split by role. Each cell filters the list below. --}}
    <x-stat-strip class="mb-5" :cells="[
        ['label' => 'All users', 'value' => number_format($total), 'note' => $statusCounts['active'].' active', 'href' => $link(['status' => $status]), 'active' => $role === 'All'],
        ['label' => 'Landlords', 'value' => number_format($roleCounts['Landlord']), 'note' => $attention['applications'] > 0 ? $attention['applications'].' application'.($attention['applications'] > 1 ? 's' : '').' waiting' : 'No applications waiting', 'href' => $link(['role' => 'Landlord', 'status' => $status]), 'active' => $role === 'Landlord'],
        ['label' => 'Tenants', 'value' => number_format($roleCounts['Tenant']), 'note' => $attention['walk_ins'] > 0 ? $attention['walk_ins'].' added by landlords' : 'All self-registered', 'href' => $link(['role' => 'Tenant', 'status' => $status]), 'active' => $role === 'Tenant'],
        ['label' => 'Admins', 'value' => number_format($roleCounts['Admin']), 'note' => 'Full access', 'href' => $link(['role' => 'Admin', 'status' => $status]), 'active' => $role === 'Admin'],
        ['label' => 'No role', 'value' => number_format($roleCounts['No role']), 'note' => 'Signed up, not set up', 'href' => $link(['role' => 'No role', 'status' => $status]), 'active' => $role === 'No role'],
    ]" />

    {{-- Search, plus the account state as tabs --}}
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <form data-live method="GET" action="{{ route('admin.users.index') }}" class="flex flex-1 flex-col gap-3 sm:flex-row lg:max-w-xl">
            @if($role !== 'All')<input type="hidden" name="role" value="{{ $role }}">@endif
            @if($status !== 'all')<input type="hidden" name="status" value="{{ $status }}">@endif
            <div class="relative flex-1">
                <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 text-[#5B6A8E]" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by name, email or phone" aria-label="Search by name, email or phone"
                    x-on:input.debounce.400ms="$el.form.requestSubmit()"
                    class="w-full h-10 pl-9 pr-4 text-[16px] sm:text-[13.5px] rounded-xl border border-[#E2E4EC] bg-white focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-all">
            </div>
            @if($search)
                <a href="{{ $link(['role' => $role, 'status' => $status, 'search' => null]) }}" class="flex h-10 items-center rounded-xl border border-[#E2E4EC] bg-white px-4 text-[13.5px] font-semibold text-[#5B6A8E] transition-colors hover:text-[#060D26]">Clear</a>
            @endif
        </form>

        <x-admin.tabs class="border-b-0" :tabs="[
            ['label' => 'Any status', 'href' => $link(['role' => $role, 'search' => $search]), 'active' => $status === 'all'],
            ['label' => 'Active', 'count' => $statusCounts['active'], 'href' => $link(['role' => $role, 'status' => 'active']), 'active' => $status === 'active'],
            ['label' => 'Suspended', 'count' => $statusCounts['suspended'], 'href' => $link(['role' => $role, 'status' => 'suspended']), 'active' => $status === 'suspended'],
            ['label' => 'Inactive', 'count' => $statusCounts['inactive'], 'href' => $link(['role' => $role, 'status' => 'inactive']), 'active' => $status === 'inactive'],
        ]" />
    </div>

    @if ($users->isEmpty())
        <x-card flush><x-admin.empty title="No users found" note="Try a different search or filter." /></x-card>
    @else
        <x-card flush>
            <div class="overflow-x-auto scrollbar-thin-light">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-[#F7F8FC] border-b border-[#E2E4EC]">
                            <x-admin.th>User</x-admin.th>
                            <x-admin.th>Contact</x-admin.th>
                            <x-admin.th>Role</x-admin.th>
                            <x-admin.th>Status</x-admin.th>
                            <x-admin.th>Joined</x-admin.th>
                            <th class="px-4 py-2.5"><span class="sr-only">Open</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E2E4EC]">
                        @foreach ($users as $user)
                            @php $accountStatus = $user->account_status ?? 'active'; @endphp
                            <tr class="hover:bg-[#F7F8FC] transition-colors duration-200">
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.users.show', $user->user_id) }}" class="flex items-center gap-3">
                                        @if ($user->profile_picture)
                                            <img loading="lazy" decoding="async" src="{{ $user->profile_picture }}" alt="" class="h-10 w-10 shrink-0 rounded-full border border-[#E2E4EC] object-cover" />
                                        @else
                                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#060D26] text-[12px] font-bold text-white">{{ strtoupper(substr($user->first_name ?? $user->email, 0, 1)) }}{{ strtoupper(substr($user->last_name ?? '', 0, 1)) }}</span>
                                        @endif
                                        <span class="min-w-0">
                                            <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                <span class="text-[13.5px] font-semibold text-[#060D26]">{{ trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: '—' }}</span>
                                                @if ($user->is_walk_in)
                                                    {{-- A landlord-entered tenant, not a self-registered account: a blank email is expected. --}}
                                                    <span class="inline-flex h-5 items-center rounded bg-[#FBBF24]/[0.16] px-1.5 text-[11px] font-semibold text-[#B45309]" title="Added by a landlord; identity not verified">Walk-in</span>
                                                @elseif (! $user->email_verified_at)
                                                    <span class="inline-flex h-5 items-center rounded bg-[#ECEEF6] px-1.5 text-[11px] font-semibold text-[#5B6A8E]" title="This email address has not been confirmed">Email not confirmed</span>
                                                @endif
                                            </span>
                                            <span class="block text-[12.5px] text-[#5B6A8E]">{{ $user->email ?: '—' }}</span>
                                        </span>
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-[13.5px] tabular-nums text-[#060D26]">{{ $user->contact_number ?: '—' }}</td>
                                <td class="px-4 py-3">
                                    @forelse ($user->roles as $userRole)
                                        <span class="mr-1 inline-flex h-6 items-center rounded-md bg-[#ECEEF6] px-2 text-[12px] font-semibold text-[#060D26]">{{ $userRole->role }}</span>
                                    @empty
                                        <span class="text-[13px] text-[#5B6A8E]">No role</span>
                                    @endforelse
                                </td>
                                <td class="px-4 py-3"><x-admin.status :status="ucfirst($accountStatus)" /></td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <p class="text-[13.5px] text-[#060D26]">{{ $user->created_at?->format('M j, Y') ?? '—' }}</p>
                                    <p class="text-[12px] text-[#5B6A8E]">{{ $user->created_at?->diffForHumans() }}</p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.users.show', $user->user_id) }}" class="inline-flex h-9 items-center rounded-lg border border-[#E2E4EC] bg-white px-3 text-[12.5px] font-semibold text-[#060D26] transition-colors duration-200 hover:bg-[#F7F8FC]">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-[#E2E4EC] px-4 py-3">
                <p class="text-[13px] text-[#5B6A8E]">Showing <span class="font-semibold tabular-nums text-[#060D26]">{{ $users->firstItem() }}–{{ $users->lastItem() }}</span> of <span class="font-semibold tabular-nums text-[#060D26]">{{ number_format($users->total()) }}</span></p>
                @if ($users->hasPages()){{ $users->links() }}@endif
            </div>
        </x-card>
    @endif

    {{-- Under the list: what needs doing, and how fast people are joining. --}}
    <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-5">
        <x-card flush class="lg:col-span-2">
            <div class="border-b border-[#E2E4EC] px-5 py-3.5">
                <h2 class="text-[15px] font-semibold text-[#060D26]">Needs attention</h2>
            </div>
            <ul class="divide-y divide-[#E2E4EC]">
                @foreach ([
                    ['Landlord applications', 'Waiting for your review', $attention['applications'], route('admin.verifications.index', ['status' => 'Pending'])],
                    ['Suspended accounts', 'Cannot sign in', $attention['suspended'], $link(['status' => 'suspended'])],
                    ['Email not confirmed', 'Signed up, never verified', $attention['unverified'], null],
                    ['Walk-in tenants', 'Added by landlords, not verified', $attention['walk_ins'], $link(['role' => 'Tenant'])],
                ] as [$label, $note, $count, $href])
                    <li>
                        <{{ $href ? 'a' : 'div' }} @if($href) href="{{ $href }}" @endif class="flex items-center justify-between gap-3 px-5 py-3 {{ $href ? 'transition-colors duration-200 hover:bg-[#F7F8FC]' : '' }}">
                            <span class="min-w-0">
                                <span class="block text-[13.5px] {{ $count > 0 ? 'font-semibold text-[#060D26]' : 'text-[#5B6A8E]' }}">{{ $label }}</span>
                                <span class="block text-[12.5px] text-[#5B6A8E]">{{ $note }}</span>
                            </span>
                            <span class="text-[18px] font-bold tabular-nums {{ $count > 0 ? 'text-[#FF8A66]' : 'text-[#5B6A8E]' }}">{{ number_format($count) }}</span>
                        </{{ $href ? 'a' : 'div' }}>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <x-card class="lg:col-span-3">
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4">
                <div>
                    <h2 class="text-[15px] font-semibold text-[#060D26]">New sign-ups</h2>
                    <p class="text-[13px] text-[#5B6A8E]">Last 30 days</p>
                </div>
                <p class="text-[13px] text-[#5B6A8E]"><span class="text-[18px] font-bold tabular-nums text-[#060D26]">{{ array_sum($signupData->all()) }}</span> joined</p>
            </div>
            <div class="h-40"><canvas id="signupChart" aria-label="New sign-ups per day for the last 30 days" role="img"></canvas></div>
        </x-card>
    </div>

</div>
@endsection

@push('scripts')
    @vite('resources/js/charts.js')
    <script>
        // Draws on load and again after live search swaps the page content (resources/js/live-filter.js).
        function drawSignupChart() {
            const el = document.getElementById('signupChart');
            if (! el || typeof Chart === 'undefined') return;
            const dark = document.documentElement.classList.contains('dark');
            new Chart(el, {
                type: 'bar',
                data: {
                    labels: @json($signupLabels),
                    datasets: [{ data: @json($signupData), backgroundColor: '#FF8A66', borderRadius: 3, maxBarThickness: 14 }],
                },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { backgroundColor: '#060D26', padding: 10, cornerRadius: 8 } },
                    scales: {
                        x: { grid: { display: false }, border: { color: dark ? 'rgba(255,255,255,0.25)' : 'rgba(6,13,38,0.25)' }, ticks: { color: dark ? '#E8ECF8' : '#060D26', font: { size: 12 }, maxTicksLimit: 6, maxRotation: 0 } },
                        y: { beginAtZero: true, suggestedMax: 3, grid: { color: dark ? 'rgba(255,255,255,0.08)' : 'rgba(6,13,38,0.06)' }, border: { display: false }, ticks: { color: dark ? '#E8ECF8' : '#060D26', font: { size: 12 }, precision: 0 } },
                    },
                },
            });
        }
        document.addEventListener('DOMContentLoaded', drawSignupChart);
        document.addEventListener('live:swapped', drawSignupChart);
    </script>
@endpush