{{--
    Shared create/edit user form. Pass `$user` to edit, omit it to create. `$modal` swaps the Cancel link
    for a button that closes the surrounding Alpine modal (`userFormOpen`). The hidden `_form` field lets
    the page reopen the right modal after a validation redirect (see old('_form') in index/show).
--}}
@php
    $user = $user ?? null;
    $modal = $modal ?? false;
    $editing = $user !== null;
    $inputBase = 'w-full h-10 px-3.5 text-[13.5px] rounded-xl border focus:outline-none focus:ring-2 focus:ring-[#FF8A66]/20 focus:border-[#FF8A66] transition-all';
    $inputOk = 'border-[#E2E4EC] bg-[#F7F8FC]';
    $inputBad = 'border-[#EF4444]/35 bg-[#EF4444]/[0.07]';
    $labelCls = 'block text-[11px] font-bold uppercase tracking-widest text-[#94A3B8] mb-1.5';
    $currentRoles = old('roles', $editing ? $user->roles->pluck('role')->toArray() : []);
@endphp

<form action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}" method="POST" class="p-5 sm:p-7 space-y-5">
    @csrf
    @if($editing) @method('PUT') @endif
    <input type="hidden" name="_form" value="{{ $editing ? 'edit-user' : 'create-user' }}">

    {{-- Name row --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label for="first_name" class="{{ $labelCls }}">First Name <span class="text-[#DC2626]">*</span></label>
            <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $user->first_name ?? '') }}" required
                class="{{ $inputBase }} {{ $errors->has('first_name') ? $inputBad : $inputOk }}">
            @error('first_name')<p class="text-[11px] text-[#DC2626] mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="last_name" class="{{ $labelCls }}">Last Name <span class="text-[#DC2626]">*</span></label>
            <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $user->last_name ?? '') }}" required
                class="{{ $inputBase }} {{ $errors->has('last_name') ? $inputBad : $inputOk }}">
            @error('last_name')<p class="text-[11px] text-[#DC2626] mt-1">{{ $message }}</p>@enderror
        </div>
    </div>

    {{-- Email --}}
    <div>
        <label for="email" class="{{ $labelCls }}">Email Address <span class="text-[#DC2626]">*</span></label>
        <input type="email" id="email" name="email" value="{{ old('email', $user->email ?? '') }}" required
            class="{{ $inputBase }} {{ $errors->has('email') ? $inputBad : $inputOk }}">
        @error('email')<p class="text-[11px] text-[#DC2626] mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Contact --}}
    <div>
        <label for="contact_number" class="{{ $labelCls }}">Contact Number</label>
        <input type="text" id="contact_number" name="contact_number" value="{{ old('contact_number', $user->contact_number ?? '') }}"
            class="{{ $inputBase }} {{ $inputOk }}">
        @error('contact_number')<p class="text-[11px] text-[#DC2626] mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Password: required on create, optional on edit --}}
    @if($editing)
        <div class="rounded-2xl border border-[#E2E4EC] bg-[#F7F8FC] p-4 space-y-3">
            <p class="text-[11px] font-bold uppercase tracking-widest text-[#94A3B8]">Change Password <span class="font-normal normal-case text-[#94A3B8]">(leave blank to keep current)</span></p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <input type="password" name="password" placeholder="New password" aria-label="New password"
                        class="{{ $inputBase }} {{ $errors->has('password') ? $inputBad : 'border-[#E2E4EC] bg-white' }}">
                    @error('password')<p class="text-[11px] text-[#DC2626] mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <input type="password" name="password_confirmation" placeholder="Confirm new password" aria-label="Confirm new password"
                        class="{{ $inputBase }} border-[#E2E4EC] bg-white">
                </div>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="password" class="{{ $labelCls }}">Password <span class="text-[#DC2626]">*</span></label>
                <input type="password" id="password" name="password" required
                    class="{{ $inputBase }} {{ $errors->has('password') ? $inputBad : $inputOk }}">
                @error('password')<p class="text-[11px] text-[#DC2626] mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="{{ $labelCls }}">Confirm Password <span class="text-[#DC2626]">*</span></label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                    class="{{ $inputBase }} {{ $inputOk }}">
            </div>
        </div>
    @endif

    {{-- Roles --}}
    <div>
        <label class="block text-[11px] font-bold uppercase tracking-widest text-[#94A3B8] mb-2">Role(s) <span class="text-[#DC2626]">*</span></label>
        <div class="flex flex-wrap gap-3">
            @foreach (['Admin', 'Landlord', 'Tenant'] as $roleOption)
                @php
                    $colors = [
                        'Admin'    => 'peer-checked:border-[#FF8A66] peer-checked:bg-[#ECEEF6] peer-checked:text-[#060D26]',
                        'Landlord' => 'peer-checked:border-[#FF8A66] peer-checked:bg-[#ECEEF6] peer-checked:text-[#060D26]',
                        'Tenant'   => 'peer-checked:border-[#22C55E] peer-checked:bg-[#22C55E]/[0.07] peer-checked:text-[#15803D]',
                    ];
                @endphp
                <label class="relative cursor-pointer">
                    <input type="checkbox" name="roles[]" value="{{ $roleOption }}"
                        {{ in_array($roleOption, $currentRoles) ? 'checked' : '' }}
                        class="peer sr-only">
                    <span class="flex items-center gap-1.5 px-4 py-2 rounded-xl border border-[#E2E4EC] bg-[#F7F8FC] text-[13px] font-semibold text-[#5B6A8E] transition-all {{ $colors[$roleOption] }}">
                        {{ $roleOption }}
                    </span>
                </label>
            @endforeach
        </div>
        @error('roles')<p class="text-[11px] text-[#DC2626] mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Status --}}
    <div>
        <label class="{{ $labelCls }}">Account Status <span class="text-[#DC2626]">*</span></label>
        <x-styled-select name="account_status" :options="['active' => 'Active', 'suspended' => 'Suspended', 'inactive' => 'Inactive']"
            :selected="old('account_status', $user->account_status ?? 'active')"
            class="w-full h-10 px-3.5 text-[13.5px] rounded-xl border border-[#E2E4EC] bg-[#F7F8FC]" />
        @error('account_status')<p class="text-[11px] text-[#DC2626] mt-1">{{ $message }}</p>@enderror
    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-end gap-3 pt-2 border-t border-[#E2E4EC]">
        @if($modal)
            <button type="button" @click="userFormOpen = false"
                class="h-10 px-5 text-[13.5px] font-semibold border border-[#E2E4EC] text-[#5B6A8E] rounded-xl hover:text-[#060D26] transition-colors cursor-pointer">
                Cancel
            </button>
        @else
            <a href="{{ $editing ? route('admin.users.show', $user) : route('admin.users.index') }}"
                class="h-10 px-5 text-[13.5px] font-semibold border border-[#E2E4EC] text-[#5B6A8E] rounded-xl hover:text-[#060D26] transition-colors flex items-center">
                Cancel
            </a>
        @endif
        <button type="submit"
            class="h-10 px-6 text-[13.5px] font-bold bg-[#FF8A66] text-[#060D26] rounded-xl hover:bg-[#E96F4F] transition-colors shadow-sm cursor-pointer">
            {{ $editing ? 'Save Changes' : 'Create User' }}
        </button>
    </div>
</form>
