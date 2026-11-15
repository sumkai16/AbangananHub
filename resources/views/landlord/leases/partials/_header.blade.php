{{-- The Rental policies header, shared by both tabs so only the content under the tabs changes when you switch. --}}
<x-page-header title="Rental policies" subtitle="The rules and wording your tenants agree to, per property and unit." :icon-path="$docIcon">
    <x-slot:actions>
        <a href="{{ route('landlord.lease-templates.create') }}"
            class="inline-flex h-10 items-center gap-1.5 rounded-xl bg-[#FF8A66] px-4 text-[13px] font-bold text-[#060D26] transition-colors hover:bg-[#E96F4F]">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
            New policy
        </a>
    </x-slot:actions>
</x-page-header>
