{{-- The three parts of Activity Logs: what admins did (tamper-evident), what happened to accounts, and everything everyone did. The export link carries the current filters. --}}
@php
    $onAudit = request()->routeIs('admin.audit-logs.*');
    $onUserActivity = request()->routeIs('admin.user-activity.*');
    $exportRoute = $onAudit ? 'admin.audit-logs.export' : ($onUserActivity ? 'admin.user-activity.export' : 'admin.security-events.export');
@endphp
<div class="mb-5 flex items-end justify-between gap-3 border-b border-[#E2E4EC]">
    <x-admin.tabs class="border-b-0" :tabs="[
        ['label' => 'Admin actions', 'href' => route('admin.audit-logs.index'), 'active' => $onAudit],
        ['label' => 'Account & security', 'href' => route('admin.security-events.index'), 'active' => request()->routeIs('admin.security-events.*')],
        ['label' => 'All user activity', 'href' => route('admin.user-activity.index'), 'active' => $onUserActivity],
    ]" />
    <a href="{{ route($exportRoute, request()->query()) }}"
        class="mb-1.5 inline-flex h-9 shrink-0 items-center gap-1.5 rounded-xl border border-[#E2E4EC] bg-white px-3.5 text-[12.5px] font-semibold text-[#060D26] transition-colors hover:bg-[#F7F8FC]">
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        Export CSV
    </a>
</div>
