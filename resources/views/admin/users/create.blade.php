@extends('layouts.admin')

@section('page-title', 'Create User')

@section('content')
<div class="max-w-2xl mx-auto">

    <a href="{{ route('admin.users.index') }}"
        class="inline-flex items-center gap-2 text-[13px] font-bold text-[#94A3B8] hover:text-[#060D26] transition-colors mb-6">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Back to users
    </a>

    <x-card flush>
        <div class="px-7 py-5 border-b border-[#E2E4EC]">
            <h1 class="text-[18px] font-normal text-[#060D26] tracking-tight">Create User</h1>
            <p class="text-[13px] text-[#94A3B8] mt-0.5">Add a new account to the system.</p>
        </div>

        @include('admin.users._form')
    </x-card>

</div>
@endsection
