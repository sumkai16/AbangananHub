@extends('layouts.admin')

@section('page-title', 'Edit User')

@section('content')
<div class="max-w-2xl mx-auto">

    <a href="{{ route('admin.users.show', $user) }}"
        class="inline-flex items-center gap-2 text-[13px] font-bold text-[#94A3B8] hover:text-[#060D26] transition-colors mb-6">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Back to user
    </a>

    <x-card flush>
        <div class="px-7 py-5 border-b border-[#E2E4EC] flex items-center gap-4">
            @php $initials = strtoupper(substr($user->first_name ?? '', 0, 1)) . strtoupper(substr($user->last_name ?? '', 0, 1)); @endphp
            <div class="w-10 h-10 rounded-2xl bg-[#FF8A66]/10 flex items-center justify-center shrink-0">
                <span class="text-[#060D26] text-[14px] font-extrabold">{{ $initials }}</span>
            </div>
            <div>
                <h1 class="text-[18px] font-normal text-[#060D26] tracking-tight">Edit User</h1>
                <p class="text-[13px] text-[#94A3B8]">{{ $user->email }}</p>
            </div>
        </div>

        @include('admin.users._form', ['user' => $user])
    </x-card>

</div>
@endsection
