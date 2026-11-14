{{-- The two halves of Activity Logs: what admins did, and what happened to accounts. --}}
<x-admin.tabs class="mb-5" :tabs="[
    ['label' => 'Admin actions', 'href' => route('admin.audit-logs.index'), 'active' => request()->routeIs('admin.audit-logs.*')],
    ['label' => 'Account & security', 'href' => route('admin.security-events.index'), 'active' => request()->routeIs('admin.security-events.*')],
]" />
