@extends('layouts.admin')

@section('page-title', 'Conversation')

@section('content')
<div class="max-w-4xl">

    {{-- Back + header --}}
    <div class="flex items-start gap-4 mb-6">
        <a href="{{ route('admin.conversations.index') }}"
            class="mt-0.5 inline-flex items-center gap-1.5 h-9 px-3.5 text-[13px] font-semibold border border-gray-200 text-gray-500 rounded-xl hover:text-[#1A1A2E] hover:bg-gray-50 transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Back
        </a>
        <div class="flex-1">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-xl font-extrabold text-[#1A1A2E] tracking-tight">Conversation Thread</h1>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border
                    {{ ($conversation->status ?? 'Open') === 'Resolved' ? 'bg-gray-50 text-gray-500 border-gray-200' : 'bg-blue-50 text-blue-700 border-blue-200' }}">
                    {{ $conversation->status ?? 'Open' }}
                </span>
                <span class="text-[12px] text-gray-400 font-medium">Read-only · Admin view</span>
            </div>
            @if($conversation->property)
                <p class="text-[13px] text-gray-500 mt-1">
                    Re: <span class="font-semibold text-[#1A1A2E]">{{ $conversation->property->title }}</span>
                    @if($conversation->unit) · Unit {{ $conversation->unit->unit_number }} @endif
                </p>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Left: message thread --}}
        <div class="lg:col-span-2">
            <div class="bg-white border border-gray-100 rounded-3xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-50 flex items-center justify-between">
                    <h2 class="text-[13.5px] font-bold text-[#1A1A2E]">Messages</h2>
                    <span class="text-[12px] text-gray-400">{{ $conversation->messages->count() }} message{{ $conversation->messages->count() !== 1 ? 's' : '' }}</span>
                </div>

                @if($conversation->messages->isEmpty())
                    <div class="p-12 text-center">
                        <svg class="w-10 h-10 text-gray-200 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                        </svg>
                        <p class="text-[13px] text-gray-400">No messages in this conversation yet.</p>
                    </div>
                @else
                    <div class="p-5 space-y-4 max-h-[600px] overflow-y-auto">
                        @php
                            $tenantId   = $conversation->tenant_id ?? null;
                            $landlordId = $conversation->landlord_id ?? null;
                        @endphp
                        @foreach($conversation->messages as $msg)
                            @php
                                $isTenant   = $msg->sender_id === $tenantId;
                                $senderName = trim(($msg->sender->first_name ?? '') . ' ' . ($msg->sender->last_name ?? '')) ?: 'Unknown';
                                $roleLabel  = $isTenant ? 'Tenant' : 'Landlord';
                            @endphp
                            <div class="flex {{ $isTenant ? 'flex-row' : 'flex-row-reverse' }} items-end gap-2.5">
                                {{-- Avatar --}}
                                <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-[12px] font-bold
                                    {{ $isTenant ? 'bg-[#286CD2]/10 text-[#286CD2]' : 'bg-emerald-50 text-emerald-600' }}">
                                    {{ strtoupper(substr($msg->sender->first_name ?? '?', 0, 1)) }}
                                </div>
                                {{-- Bubble --}}
                                <div class="max-w-[75%]">
                                    <p class="text-[10.5px] font-semibold {{ $isTenant ? 'text-[#286CD2]' : 'text-emerald-600 text-right' }} mb-1">
                                        {{ $senderName }} · {{ $roleLabel }}
                                    </p>
                                    <div class="px-4 py-2.5 rounded-2xl text-[13px] leading-relaxed
                                        {{ $isTenant
                                            ? 'bg-[#286CD2]/8 text-[#1A1A2E] rounded-bl-sm border border-[#286CD2]/10'
                                            : 'bg-gray-100 text-[#1A1A2E] rounded-br-sm border border-gray-200' }}">
                                        {{ $msg->message }}
                                    </div>
                                    <p class="text-[10.5px] text-gray-400 mt-1 {{ $isTenant ? '' : 'text-right' }}">
                                        {{ $msg->sent_at?->format('M d, Y · g:i A') ?? $msg->created_at->format('M d, Y · g:i A') }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="px-6 py-3 border-t border-gray-50 bg-amber-50/50">
                    <p class="text-[11px] text-amber-700/80 font-medium text-center">
                        Admin view only — you cannot send messages in this conversation.
                    </p>
                </div>
            </div>
        </div>

        {{-- Right: participants + property --}}
        <div class="space-y-4">

            {{-- Tenant card --}}
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
                <p class="text-[10.5px] font-bold uppercase tracking-widest text-gray-400 mb-2.5">Tenant</p>
                @if($conversation->tenant)
                    <div class="flex items-center gap-2.5 mb-3">
                        <div class="w-9 h-9 rounded-full bg-[#286CD2]/10 flex items-center justify-center text-[13px] font-bold text-[#286CD2]">
                            {{ strtoupper(substr($conversation->tenant->first_name ?? '?', 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-[13px] font-bold text-[#1A1A2E]">
                                {{ trim($conversation->tenant->first_name . ' ' . $conversation->tenant->last_name) }}
                            </p>
                            <p class="text-[11px] text-gray-400">{{ $conversation->tenant->email }}</p>
                        </div>
                    </div>
                    <a href="{{ route('admin.users.show', $conversation->tenant) }}"
                        class="block w-full text-center py-2 text-[12px] font-semibold border border-gray-200 text-gray-500 rounded-xl hover:bg-gray-50 transition-colors">
                        View Profile
                    </a>
                @else
                    <p class="text-[13px] text-gray-400 italic">No tenant linked.</p>
                @endif
            </div>

            {{-- Landlord card --}}
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
                <p class="text-[10.5px] font-bold uppercase tracking-widest text-gray-400 mb-2.5">Landlord</p>
                @if($conversation->landlord)
                    <div class="flex items-center gap-2.5 mb-3">
                        <div class="w-9 h-9 rounded-full bg-emerald-50 flex items-center justify-center text-[13px] font-bold text-emerald-600">
                            {{ strtoupper(substr($conversation->landlord->first_name ?? '?', 0, 1)) }}
                        </div>
                        <div>
                            <p class="text-[13px] font-bold text-[#1A1A2E]">
                                {{ trim($conversation->landlord->first_name . ' ' . $conversation->landlord->last_name) }}
                            </p>
                            <p class="text-[11px] text-gray-400">{{ $conversation->landlord->email }}</p>
                        </div>
                    </div>
                    <a href="{{ route('admin.users.show', $conversation->landlord) }}"
                        class="block w-full text-center py-2 text-[12px] font-semibold border border-gray-200 text-gray-500 rounded-xl hover:bg-gray-50 transition-colors">
                        View Profile
                    </a>
                @else
                    <p class="text-[13px] text-gray-400 italic">No landlord linked.</p>
                @endif
            </div>

            {{-- Property card --}}
            @if($conversation->property)
                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
                    <p class="text-[10.5px] font-bold uppercase tracking-widest text-gray-400 mb-2.5">Property</p>
                    <p class="text-[13.5px] font-bold text-[#1A1A2E] mb-0.5">{{ $conversation->property->title }}</p>
                    <p class="text-[12px] text-gray-400 mb-1">{{ $conversation->property->address ?? '' }}</p>
                    @if($conversation->unit)
                        <p class="text-[12px] font-semibold text-[#286CD2]">Unit {{ $conversation->unit->unit_number }}</p>
                    @endif
                </div>
            @endif

            {{-- Meta --}}
            <div class="bg-gray-50 border border-gray-100 rounded-2xl p-4">
                <p class="text-[10.5px] font-bold uppercase tracking-widest text-gray-400 mb-2.5">Details</p>
                <div class="space-y-2 text-[12.5px]">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Started</span>
                        <span class="font-semibold text-[#1A1A2E]">{{ $conversation->created_at->format('M d, Y') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Last activity</span>
                        <span class="font-semibold text-[#1A1A2E]">{{ $conversation->updated_at->diffForHumans() }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Status</span>
                        <span class="font-bold {{ ($conversation->status ?? 'Open') === 'Resolved' ? 'text-gray-500' : 'text-blue-600' }}">
                            {{ $conversation->status ?? 'Open' }}
                        </span>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
