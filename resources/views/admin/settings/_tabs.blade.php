{{-- Two tabs under System Settings: platform rules, and the admin's own two-factor sign-in. --}}
<x-admin.tabs class="mb-5" :tabs="[
    ['label' => 'Platform settings', 'href' => route('admin.settings.edit'), 'active' => request()->routeIs('admin.settings.*')],
    ['label' => 'Two-factor sign-in', 'href' => route('admin.security.two-factor'), 'active' => request()->routeIs('admin.security.two-factor*')],
]" />
