@extends('layouts.admin')

@section('page-title', 'Tenant holds')

@section('content')
<div class="max-w-[1600px] mx-auto">

    <x-page-header icon-path="M14.25 9v6m-4.5 0V9M21 12a9 9 0 11-18 0 9 9 0 0118 0z" title="Tenant holds"
        subtitle="Landlords report tenants who have not paid. Confirm a report to put the tenant on hold; they can still sign in and pay." />

    <x-admin.tabs class="mb-5" :tabs="[
        ['label' => 'Pending', 'href' => route('admin.tenant-holds.index', ['tab' => 'pending']), 'active' => $tab === 'pending', 'count' => $counts['pending']],
        ['label' => 'On hold', 'href' => route('admin.tenant-holds.index', ['tab' => 'active']), 'active' => $tab === 'active', 'count' => $counts['active']],
        ['label' => 'Possible duplicates', 'href' => route('admin.tenant-holds.index', ['tab' => 'duplicates']), 'active' => $tab === 'duplicates', 'count' => $counts['duplicates']],
        ['label' => 'History', 'href' => route('admin.tenant-holds.index', ['tab' => 'history']), 'active' => $tab === 'history', 'count' => $counts['history']],
    ]" />

    @if($tab === 'duplicates')
        @include('admin.tenant-holds._duplicates')
    @else
        @include('admin.tenant-holds._holds')
    @endif

</div>
@endsection
