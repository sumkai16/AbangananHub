@extends(auth()->user()->usesLandlordShell() ? 'layouts.landlord' : 'layouts.app', ['searchBar' => false])

@section('content')
    @php
        $status = request('status', 'all');
        $flagPath = 'M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5';
        $statusPill = [
            'Pending' => 'bg-[#FBBF24]/[0.12] text-[#92400E]',
            'Resolved' => 'bg-[#22C55E]/[0.10] text-[#15803D]',
        ];

        // One row model for both layouts, so the phone cards and the desktop table can't drift apart.
        $rows = $reports->map(function ($report) {
            if ($report->property) {
                $target = ['name' => $report->property->title, 'kind' => 'Listing', 'listing' => true];
            } elseif ($report->reportedUser) {
                $target = ['name' => trim($report->reportedUser->first_name.' '.$report->reportedUser->last_name), 'kind' => 'User', 'listing' => false];
            } else {
                $target = ['name' => 'No longer available', 'kind' => null, 'listing' => null];
            }

            return [
                'target' => $target,
                'reason' => $report->report_reason,
                'status' => $report->report_status === 'Resolved' ? 'Resolved' : 'Pending',
                'note' => $report->report_status === 'Resolved' ? $report->admin_notes : null,
                'date' => $report->created_at->format('M j, Y'),
            ];
        });
    @endphp

    <div class="{{ auth()->user()->shellContainerClass() }} mx-auto px-4 sm:px-6 lg:px-8 py-8 pb-14 min-h-[calc(100vh-72px)] flex flex-col">

        {{-- Header --}}
        <x-page-header title="My Reports" subtitle="Track the status of reports you have filed." :icon-path="$flagPath">
            <x-slot:actions>
                <button type="button" x-data x-on:click="$dispatch('open-file-report')"
                    class="inline-flex h-11 cursor-pointer items-center gap-1.5 rounded-xl bg-[#FF8A66] px-5 text-[13.5px] font-semibold text-[#060D26] transition-all duration-200 hover:bg-[#E96F4F]">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Report a problem
                </button>
            </x-slot:actions>
        </x-page-header>

        {{-- Summary cards --}}
        <div class="stat-chips grid grid-cols-3 gap-3 mb-6">
            <x-stat-card label="Total" :value="$stats['total']">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $flagPath }}" /></svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="Pending" :value="$stats['pending']" sub="With our team" icon-bg="rgba(251,191,36,0.12)">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#B45309" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="Resolved" :value="$stats['resolved']" value-color="#15803D" icon-bg="rgba(34,197,94,0.07)">
                <x-slot:icon>
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#16A34A" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                </x-slot:icon>
            </x-stat-card>
        </div>

        {{-- Status tabs --}}
        <div class="flex items-center gap-1 border-b border-[#5B6A8E]/15 mb-5 overflow-x-auto">
            @foreach(['all' => 'All', 'Pending' => 'Pending', 'Resolved' => 'Resolved'] as $key => $label)
                <a href="{{ route('tenant.reports.index', $key === 'all' ? [] : ['status' => $key]) }}" data-page-swap="nav"
                    class="px-4 py-2.5 text-sm font-semibold border-b-2 transition-colors duration-150 whitespace-nowrap
                              {{ $status === $key ? 'border-[#FF8A66] text-[#060D26]' : 'border-transparent text-[#5B6A8E] hover:text-[#060D26]' }}">
                    {{ $label }}
                    <span class="ml-1 text-[11px]">{{ $key === 'all' ? $stats['total'] : $stats[strtolower($key)] }}</span>
                </a>
            @endforeach
        </div>

        {{-- Reports --}}
        <x-card flush class="flex-1 flex flex-col">
            @if($rows->isEmpty())
                <div class="flex-1 flex flex-col items-center justify-center py-16 px-6 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-[#ECEEF6] flex items-center justify-center mb-4">
                        <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#060D26" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $flagPath }}" /></svg>
                    </div>
                    <p class="text-[15px] font-bold text-[#060D26] mb-1">No reports {{ $status !== 'all' ? 'with this status' : 'filed yet' }}</p>
                    <p class="text-[13px] text-[#5B6A8E] mb-5 max-w-xs">Scams, fake listings or bad behaviour: tell us and track what happens next here.</p>
                    @if($status === 'all')
                        <button type="button" x-data x-on:click="$dispatch('open-file-report')"
                            class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full text-[13px] font-semibold text-[#060D26] bg-[#FF8A66] hover:bg-[#E96F4F] transition-all shadow-sm cursor-pointer">
                            Report a problem
                        </button>
                    @else
                        <a href="{{ route('tenant.reports.index') }}" data-page-swap="nav"
                            class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full text-[13px] font-semibold text-[#060D26] bg-white ring-1 ring-[#5B6A8E]/15 hover:bg-[#ECEEF6] transition-all">
                            View all reports
                        </a>
                    @endif
                </div>
            @else
                {{-- Phones and tablets: one card per report --}}
                <ul class="lg:hidden divide-y divide-[#E2E4EC]">
                    @foreach($rows as $row)
                        <li class="p-4">
                            <div class="flex items-start gap-3">
                                @include('tenant.reports._target-avatar', ['target' => $row['target']])
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="min-w-0 text-[14px] font-semibold leading-snug text-[#060D26]">{{ $row['target']['name'] }}</p>
                                        <span class="shrink-0 inline-flex items-center h-6 px-2.5 rounded-full text-[11px] font-bold whitespace-nowrap {{ $statusPill[$row['status']] }}">{{ $row['status'] }}</span>
                                    </div>
                                    <p class="mt-0.5 text-[12.5px] text-[#5B6A8E]">{{ $row['target']['kind'] ?? 'Removed' }} · {{ $row['date'] }}</p>
                                    <p class="mt-1.5 text-[13px] leading-relaxed text-[#060D26]">{{ $row['reason'] }}</p>
                                    @if($row['note'])
                                        <p class="mt-2 rounded-lg bg-[#F7F8FC] px-3 py-2 text-[12.5px] leading-relaxed text-[#5B6A8E]"><span class="font-semibold text-[#060D26]">Admin:</span> {{ $row['note'] }}</p>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{-- Desktop table --}}
                <div class="hidden lg:block">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-[#F7F8FC] text-[11px] font-bold text-[#5B6A8E] uppercase tracking-wider">
                                <th class="w-[28%] px-5 py-3">Reported</th>
                                <th class="px-4 py-3">What you said</th>
                                <th class="w-px whitespace-nowrap px-4 py-3">Status</th>
                                <th class="w-px whitespace-nowrap px-5 py-3 text-right">Filed</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E2E4EC]">
                            @foreach($rows as $row)
                                <tr class="align-top hover:bg-[#F7F8FC] transition-colors duration-150">
                                    <td class="max-w-0 px-5 py-3.5">
                                        <div class="flex items-center gap-3 min-w-0">
                                            @include('tenant.reports._target-avatar', ['target' => $row['target']])
                                            <div class="min-w-0">
                                                <p class="text-[14px] font-semibold truncate text-[#060D26]" title="{{ $row['target']['name'] }}">{{ $row['target']['name'] }}</p>
                                                <p class="text-[12.5px] text-[#5B6A8E]">{{ $row['target']['kind'] ?? 'Removed' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <p class="text-[13.5px] leading-relaxed text-[#060D26]">{{ $row['reason'] }}</p>
                                        @if($row['note'])
                                            <p class="mt-1.5 text-[12.5px] leading-relaxed text-[#5B6A8E]"><span class="font-semibold text-[#060D26]">Admin:</span> {{ $row['note'] }}</p>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3.5">
                                        <span class="inline-flex items-center h-6 px-2.5 rounded-full text-[11px] font-bold whitespace-nowrap {{ $statusPill[$row['status']] }}">{{ $row['status'] }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-3.5 text-right text-[13px] text-[#5B6A8E] tabular-nums">{{ $row['date'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        @if($reports->hasPages())
            <div class="mt-5">
                {{ $reports->links() }}
            </div>
        @endif
    </div>

    @include('reports._file-modal', ['openOnLoad' => $openReport, 'defaultTarget' => 'property'])
@endsection
