@extends('layouts.admin')

@section('page-title', 'Create User')

@section('content')
<div class="max-w-2xl">

    <a href="{{ route('admin.users.index') }}"
        class="inline-flex items-center gap-2 text-[13px] font-bold text-[#94A3B8] hover:text-[#156F8C] transition-colors mb-6">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
        </svg>
        Back to users
    </a>

    <div class="bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)] overflow-hidden">
        <div class="px-7 py-5 border-b border-[#E2E8F0]">
            <h1 class="text-[18px] font-extrabold text-[#1F2937] tracking-tight">Create User</h1>
            <p class="text-[13px] text-[#94A3B8] mt-0.5">Add a new account to the system.</p>
        </div>

        <form action="{{ route('admin.users.store') }}" method="POST" class="p-5 sm:p-7 space-y-5">
            @csrf

            {{-- Name row --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="first_name" class="block text-[11px] font-bold uppercase tracking-widest text-[#94A3B8] mb-1.5">First Name <span class="text-[#DC2626]">*</span></label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required
                        class="w-full h-10 px-3.5 text-[13.5px] rounded-xl border {{ $errors->has('first_name') ? 'border-[#EF4444]/35 bg-[#EF4444]/[0.07]' : 'border-[#E2E8F0] bg-[#F7FCFC]' }} focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all">
                    @error('first_name')<p class="text-[11px] text-[#DC2626] mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="last_name" class="block text-[11px] font-bold uppercase tracking-widest text-[#94A3B8] mb-1.5">Last Name <span class="text-[#DC2626]">*</span></label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required
                        class="w-full h-10 px-3.5 text-[13.5px] rounded-xl border {{ $errors->has('last_name') ? 'border-[#EF4444]/35 bg-[#EF4444]/[0.07]' : 'border-[#E2E8F0] bg-[#F7FCFC]' }} focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all">
                    @error('last_name')<p class="text-[11px] text-[#DC2626] mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            {{-- Email --}}
            <div>
                <label for="email" class="block text-[11px] font-bold uppercase tracking-widest text-[#94A3B8] mb-1.5">Email Address <span class="text-[#DC2626]">*</span></label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required
                    class="w-full h-10 px-3.5 text-[13.5px] rounded-xl border {{ $errors->has('email') ? 'border-[#EF4444]/35 bg-[#EF4444]/[0.07]' : 'border-[#E2E8F0] bg-[#F7FCFC]' }} focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all">
                @error('email')<p class="text-[11px] text-[#DC2626] mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Contact --}}
            <div>
                <label for="contact_number" class="block text-[11px] font-bold uppercase tracking-widest text-[#94A3B8] mb-1.5">Contact Number</label>
                <input type="text" id="contact_number" name="contact_number" value="{{ old('contact_number') }}"
                    class="w-full h-10 px-3.5 text-[13.5px] rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all">
                @error('contact_number')<p class="text-[11px] text-[#DC2626] mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Password --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="password" class="block text-[11px] font-bold uppercase tracking-widest text-[#94A3B8] mb-1.5">Password <span class="text-[#DC2626]">*</span></label>
                    <input type="password" id="password" name="password" required
                        class="w-full h-10 px-3.5 text-[13.5px] rounded-xl border {{ $errors->has('password') ? 'border-[#EF4444]/35 bg-[#EF4444]/[0.07]' : 'border-[#E2E8F0] bg-[#F7FCFC]' }} focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all">
                    @error('password')<p class="text-[11px] text-[#DC2626] mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-[11px] font-bold uppercase tracking-widest text-[#94A3B8] mb-1.5">Confirm Password <span class="text-[#DC2626]">*</span></label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required
                        class="w-full h-10 px-3.5 text-[13.5px] rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all">
                </div>
            </div>

            {{-- Roles --}}
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-widest text-[#94A3B8] mb-2">Role(s) <span class="text-[#DC2626]">*</span></label>
                <div class="flex flex-wrap gap-3">
                    @foreach (['Admin', 'Landlord', 'Tenant'] as $roleOption)
                        @php
                            $colors = [
                                'Admin'    => 'peer-checked:border-[#2AA7A1] peer-checked:bg-[#EEF8F8] peer-checked:text-[#156F8C]',
                                'Landlord' => 'peer-checked:border-[#2AA7A1] peer-checked:bg-[#EEF8F8] peer-checked:text-[#156F8C]',
                                'Tenant'   => 'peer-checked:border-[#22C55E] peer-checked:bg-[#22C55E]/[0.07] peer-checked:text-[#15803D]',
                            ];
                        @endphp
                        <label class="relative cursor-pointer">
                            <input type="checkbox" name="roles[]" value="{{ $roleOption }}"
                                {{ in_array($roleOption, old('roles', [])) ? 'checked' : '' }}
                                class="peer sr-only">
                            <span class="flex items-center gap-1.5 px-4 py-2 rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] text-[13px] font-semibold text-[#64748B] transition-all {{ $colors[$roleOption] }}">
                                {{ $roleOption }}
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('roles')<p class="text-[11px] text-[#DC2626] mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Status --}}
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-widest text-[#94A3B8] mb-1.5">Account Status <span class="text-[#DC2626]">*</span></label>
                <select name="account_status"
                    class="w-full h-10 px-3.5 text-[13.5px] rounded-xl border border-[#E2E8F0] bg-[#F7FCFC] focus:outline-none focus:ring-2 focus:ring-[#2AA7A1]/20 focus:border-[#2AA7A1] transition-all">
                    @foreach (['active' => 'Active', 'suspended' => 'Suspended', 'inactive' => 'Inactive'] as $val => $label)
                        <option value="{{ $val }}" {{ old('account_status', 'active') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('account_status')<p class="text-[11px] text-[#DC2626] mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Actions --}}
            <div class="flex items-center justify-end gap-3 pt-2 border-t border-[#E2E8F0]">
                <a href="{{ route('admin.users.index') }}"
                    class="h-10 px-5 text-[13.5px] font-semibold border border-[#E2E8F0] text-[#64748B] rounded-xl hover:text-[#1F2937] hover:border-[#E2E8F0] transition-colors flex items-center">
                    Cancel
                </a>
                <button type="submit"
                    class="h-10 px-6 text-[13.5px] font-bold bg-[#2AA7A1] text-white rounded-xl hover:brightness-95 transition-colors shadow-sm">
                    Create User
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
