{{--
    Emergency contact and ID on file for this tenancy's tenant. The ID number is hidden until the landlord
    taps Show (shoulder-surfing, and the page is often open on a shared phone). A walk-in cannot log in, so
    for them the landlord can fill the details in here; an online tenant edits their own in Account Settings.
    Expects: $reservation (tenant.tenantDetail loaded).
--}}
@can('viewTenantDetails', $reservation)
    @php
        $detail = $reservation->tenant?->tenantDetail;
        $canEditDetails = Gate::allows('editWalkInDetails', $reservation);
        $hasErrors = $errors->hasAny(\App\Models\TenantDetail::FIELDS);
        // Echoed with {{ }} below: Blade does not compile @js() inside a <x-card ...> attribute.
        $idUrl = route('landlord.tenancies.tenantDetails.id', $reservation);
    @endphp

    <x-card x-data="{
            showId: false, fullId: null, loadingId: false, idError: '',
            editing: {{ $hasErrors ? 'true' : 'false' }},
            async toggleId() {
                this.idError = '';
                if (this.showId) { this.showId = false; this.fullId = null; return; }
                this.loadingId = true;
                try {
                    const res = await fetch('{{ $idUrl }}', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                    if (!res.ok) throw new Error('failed');
                    this.fullId = (await res.json()).id_number;
                    this.showId = true;
                } catch (e) {
                    this.idError = 'Could not load the ID number. Try again.';
                } finally {
                    this.loadingId = false;
                }
            }
        }" class="!p-5 sm:!p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-[16px] font-semibold text-[#060D26]">Emergency contact &amp; ID</h2>
                <p class="text-[12px] text-[#5B6A8E] mt-0.5">Kept private to you as the landlord of this property.</p>
            </div>
            @if ($canEditDetails)
                <button type="button" @click="editing = !editing" x-text="editing ? 'Cancel' : '{{ $detail ? 'Edit' : 'Add details' }}'"
                    class="h-9 px-4 rounded-xl border border-[#060D26]/25 bg-white hover:bg-[#F7F8FC] text-[13px] font-semibold text-[#060D26] cursor-pointer transition-colors duration-200"></button>
            @endif
        </div>

        <div x-show="!editing" class="mt-4">
            @if ($detail && ($detail->emergency_contact_name || $detail->id_type))
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-[13.5px]">
                    <div>
                        <dt class="text-[12px] font-semibold text-[#5B6A8E]">Emergency contact</dt>
                        <dd class="mt-0.5 text-[#060D26] font-medium">
                            {{ $detail->emergency_contact_name ?: '—' }}
                            @if ($detail->emergency_contact_relationship)
                                <span class="font-normal text-[#5B6A8E]">({{ $detail->emergency_contact_relationship }})</span>
                            @endif
                        </dd>
                        @if ($detail->emergency_contact_number)
                            <dd class="mt-0.5">
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $detail->emergency_contact_number) }}"
                                    class="text-[#060D26] font-semibold underline underline-offset-2 tabular-nums">{{ $detail->emergency_contact_number }}</a>
                            </dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-[12px] font-semibold text-[#5B6A8E]">ID shown</dt>
                        <dd class="mt-0.5 text-[#060D26] font-medium">{{ $detail->id_type ?: '—' }}</dd>
                        @if ($detail->id_number)
                            <dd class="mt-0.5 flex flex-wrap items-center gap-3">
                                <span class="tabular-nums text-[#060D26]" x-text="showId && fullId ? fullId : @js($detail->maskedIdNumber())">{{ $detail->maskedIdNumber() }}</span>
                                <button type="button" :disabled="loadingId" @click="toggleId()"
                                    x-text="loadingId ? 'Loading…' : (showId ? 'Hide' : 'Show')"
                                    class="text-[12px] font-semibold text-[#5B6A8E] hover:text-[#060D26] underline underline-offset-2 cursor-pointer disabled:opacity-60 disabled:cursor-wait"></button>
                                <span x-show="idError" x-cloak x-text="idError" class="text-[12px] text-[#B91C1C]"></span>
                            </dd>
                        @endif
                    </div>
                </dl>
            @else
                <p class="text-[13.5px] text-[#5B6A8E]">
                    @if ($reservation->tenant?->is_walk_in)
                        Nothing on file yet. Add the tenant's emergency contact and ID so you have them if something happens.
                    @else
                        This tenant has not added an emergency contact yet.
                    @endif
                </p>
            @endif
        </div>

        @if ($canEditDetails)
            <form x-show="editing" x-cloak method="POST" action="{{ route('landlord.tenancies.tenantDetails.update', $reservation) }}" class="mt-4">
                @csrf @method('PATCH')
                <x-tenant-details-fields :detail="$detail" id-prefix="tenancy" />
                <p class="mt-3 text-[12px] text-[#5B6A8E]">Only the fields you fill in are saved. The ID number is stored encrypted.</p>
                <button type="submit"
                    class="mt-4 h-10 px-5 rounded-xl bg-[#FF8A66] hover:bg-[#E96F4F] text-[#060D26] text-sm font-semibold cursor-pointer transition-colors duration-200">
                    Save details
                </button>
            </form>
        @endif
    </x-card>
@endcan
