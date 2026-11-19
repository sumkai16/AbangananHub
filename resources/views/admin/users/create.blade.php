@extends('layouts.admin')

@section('page-title', 'Create User')

@section('content')
<div class="max-w-2xl mx-auto">

    <x-back-link href="{{ route('admin.users.index') }}" to="users" class="mb-6" />

    <x-card flush>
        <div class="px-7 py-5 border-b border-[#E2E4EC]">
            <h1 class="text-[18px] font-semibold text-[#060D26] tracking-tight">Create User</h1>
            <p class="text-[13px] text-[#5B6A8E] mt-0.5">Add a new account to the system.</p>
        </div>

        @include('admin.users._form')
    </x-card>

</div>
@endsection
