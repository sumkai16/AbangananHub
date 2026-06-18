@extends('layouts.app')

@section('hide_search', true) {{-- This line intercepts and hides the navigation search block --}}

@section('content')
<div class="relative min-h-screen overflow-hidden bg-slate-50 py-12">

    <div class="absolute inset-0 -z-10">
        <div class="absolute top-0 right-0 h-[500px] w-[500px] rounded-full bg-blue-100/40 blur-3xl"></div>
        <div class="absolute bottom-0 left-0 h-[400px] w-[400px] rounded-full bg-indigo-100/30 blur-3xl"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(59,130,246,0.08),_transparent_40%)]"></div>
    </div>

    <div class="max-w-7xl mx-auto px-6 lg:px-8">
        
        <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between border-b border-slate-200/70 pb-6 mb-8">
            <div class="space-y-3">
                <a href="{{ route('landlord.listings.index') }}" class="group inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-blue-600 bg-white border border-slate-200/80 px-3 py-1.5 rounded-xl shadow-sm transition-all duration-200 hover:shadow-md hover:-translate-y-0.5">
                    <svg class="w-3.5 h-3.5 transform group-hover:-translate-x-0.5 transition-transform stroke-[2.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Listings
                </a>
                
                <div class="flex items-start gap-4">
                    <div class="hidden sm:flex h-12 w-12 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600 shadow-sm shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-3xl font-black text-slate-900 tracking-tight leading-none sm:mt-0.5">Create New Listing</h1>
                        <p class="text-sm text-slate-500 mt-2 font-medium max-w-2xl">Configure details below to deploy your property asset onto the verification network pipeline.</p>
                    </div>
                </div>
            </div>
        </div>

        @if($errors->any())
            <div class="mb-8 p-5 bg-red-50/90 border border-red-200 rounded-2xl flex gap-4 shadow-lg shadow-red-500/5 backdrop-blur-sm">
                <div class="w-9 h-9 rounded-xl bg-red-100 flex items-center justify-center shrink-0 text-red-600 shadow-sm">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h5 class="text-[14px] font-bold text-slate-900">Please resolve validation warnings:</h5>
                    <ul class="list-disc pl-4 mt-1 text-[13px] text-red-700 space-y-0.5 font-medium">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <form action="{{ route('properties.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <div class="lg:col-span-7 bg-white/80 backdrop-blur-xl border border-white/70 rounded-[28px] p-8 shadow-[0_20px_60px_-15px_rgba(15,23,42,0.08)] space-y-6">
                    
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                        <div class="h-10 w-10 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Core Information</h3>
                            <p class="text-sm text-slate-500">Manage the essential details of your new property.</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Property Name / Title</label>
                        <input type="text" name="title" value="{{ old('title') }}" minlength="10" maxlength="150" class="w-full h-12 px-4 rounded-2xl border @error('title') border-red-300 bg-red-50/30 @else border-slate-200 bg-white/70 @enderror outline-none text-[14px] font-medium text-slate-800 shadow-sm transition-all duration-300 hover:border-slate-300 focus:shadow-[0_0_0_6px_rgba(59,130,246,0.08)] focus:border-blue-500 focus:-translate-y-[1px]" placeholder="e.g., Luxury Studio Unit near IT Park" required>
                        @error('title')
                            <span class="text-xs font-semibold text-red-500 mt-1.5 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Classification Type</label>
                            <div class="relative">
                                <select name="property_type" class="w-full h-12 px-4 rounded-2xl border @error('property_type') border-red-300 bg-red-50/30 @else border-slate-200 bg-white/70 @enderror outline-none text-[14px] font-medium text-slate-800 appearance-none shadow-sm transition-all duration-300 hover:border-slate-300 focus:shadow-[0_0_0_6px_rgba(59,130,246,0.08)] focus:border-blue-500 focus:-translate-y-[1px]" required>
                                    <option value="">Select an arrangement</option>
                                    <option value="Bedspace" {{ old('property_type') == 'Bedspace' ? 'selected' : '' }}>Bedspace</option>
                                    <option value="Room" {{ old('property_type') == 'Room' ? 'selected' : '' }}>Room</option>
                                    <option value="Apartment" {{ old('property_type') == 'Apartment' ? 'selected' : '' }}>Apartment</option>
                                    <option value="House" {{ old('property_type') == 'House' ? 'selected' : '' }}>House</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            </div>
                            @error('property_type')
                                <span class="text-xs font-semibold text-red-500 mt-1.5 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>{{ $message }}</span>
                            @enderror
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Monthly Fee (PHP)</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 font-bold text-[14px]">₱</span>
                                <input type="number" step="0.01" min="500" max="999999" name="rental_fee" value="{{ old('rental_fee') }}" class="w-full h-12 pl-8 pr-4 rounded-2xl border @error('rental_fee') border-red-300 bg-red-50/30 @else border-slate-200 bg-white/70 @enderror outline-none text-[14px] font-bold text-slate-800 shadow-sm transition-all duration-300 hover:border-slate-300 focus:shadow-[0_0_0_6px_rgba(59,130,246,0.08)] focus:border-blue-500 focus:-translate-y-[1px]" placeholder="0.00" required>
                            </div>
                            @error('rental_fee')
                                <span class="text-xs font-semibold text-red-500 mt-1.5 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t border-slate-100 pt-5">
                        <div class="sm:col-span-1">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Occupancy Limit</label>
                            <input type="number" name="occupancy_limit" value="{{ old('occupancy_limit', 1) }}" min="1" max="100" class="w-full h-12 px-4 rounded-2xl border @error('occupancy_limit') border-red-300 bg-red-50/30 @else border-slate-200 bg-white/70 @enderror outline-none text-[14px] font-medium text-slate-800 shadow-sm transition-all duration-300 hover:border-slate-300 focus:shadow-[0_0_0_6px_rgba(59,130,246,0.08)] focus:border-blue-500 focus:-translate-y-[1px]" required>
                            @error('occupancy_limit')
                                <span class="text-xs font-semibold text-red-500 mt-1.5 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="sm:col-span-1">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Latitude</label>
                            <input type="number" step="any" min="-90" max="90" name="latitude" value="{{ old('latitude') }}" placeholder="10.3157" class="w-full h-12 px-4 rounded-2xl border @error('latitude') border-red-300 bg-red-50/30 @else border-slate-200 bg-white/70 @enderror outline-none text-[14px] text-slate-700 shadow-sm transition-all duration-300 hover:border-slate-300 focus:shadow-[0_0_0_6px_rgba(59,130,246,0.08)] focus:border-blue-500 focus:-translate-y-[1px]">
                            @error('latitude')
                                <span class="text-xs font-semibold text-red-500 mt-1.5 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="sm:col-span-1">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Longitude</label>
                            <input type="number" step="any" min="-180" max="180" name="longitude" value="{{ old('longitude') }}" placeholder="123.8854" class="w-full h-12 px-4 rounded-2xl border @error('longitude') border-red-300 bg-red-50/30 @else border-slate-200 bg-white/70 @enderror outline-none text-[14px] text-slate-700 shadow-sm transition-all duration-300 hover:border-slate-300 focus:shadow-[0_0_0_6px_rgba(59,130,246,0.08)] focus:border-blue-500 focus:-translate-y-[1px]">
                            @error('longitude')
                                <span class="text-xs font-semibold text-red-500 mt-1.5 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Complete Structural Address</label>
                        <input type="text" name="address" value="{{ old('address') }}" minlength="10" maxlength="255" class="w-full h-12 px-4 rounded-2xl border @error('address') border-red-300 bg-red-50/30 @else border-slate-200 bg-white/70 @enderror outline-none text-[14px] font-medium text-slate-800 shadow-sm transition-all duration-300 hover:border-slate-300 focus:shadow-[0_0_0_6px_rgba(59,130,246,0.08)] focus:border-blue-500 focus:-translate-y-[1px]" placeholder="Building, Street name, Barangay, City" required>
                        @error('address')
                            <span class="text-xs font-semibold text-red-500 mt-1.5 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Property Description Portfolio</label>
                        <textarea name="description" rows="6" minlength="20" maxlength="3000" class="w-full p-4 rounded-2xl border @error('description') border-red-300 bg-red-50/30 @else border-slate-200 bg-white/70 @enderror outline-none text-[14px] font-medium text-slate-800 leading-relaxed shadow-sm transition-all duration-300 hover:border-slate-300 focus:shadow-[0_0_0_6px_rgba(59,130,246,0.08)] focus:border-blue-500 focus:-translate-y-[1px]" placeholder="Describe amenities, nearby establishments, house rules, payment terms..." required>{{ old('description') }}</textarea>
                        @error('description')
                            <span class="text-xs font-semibold text-red-500 mt-1.5 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="lg:col-span-5">
                    <div class="sticky top-8 space-y-6">
                        
                        <div class="bg-white/80 backdrop-blur-xl border border-white/70 rounded-[28px] p-6 shadow-[0_20px_60px_-15px_rgba(15,23,42,0.08)] space-y-4">
                            <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                                <div class="h-8 w-8 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                                <h3 class="text-sm font-bold text-slate-900">Media System Attachments</h3>
                            </div>
                            
                            <div class="border-2 border-dashed @error('photos') border-red-300 bg-red-50/10 @else border-slate-200/80 @enderror hover:border-blue-400 rounded-2xl p-6 bg-gradient-to-b from-slate-50/40 to-slate-50 hover:from-white hover:to-blue-50/10 text-center transition-all duration-300 group relative shadow-inner">
                                <label class="cursor-pointer block">
                                    <div class="w-12 h-12 rounded-2xl bg-white shadow-md border border-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-400 group-hover:text-blue-600 group-hover:scale-110 group-hover:shadow-blue-500/5 transition-all duration-300">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <span id="upload-label" class="text-[14px] font-bold text-slate-800 transition-colors group-hover:text-blue-600">Select Photos to Upload</span>
                                    <p class="text-[12px] text-slate-400 mt-1">Accepts JPEG, PNG, WEBP profiles (Max 5MB per file setup)</p>
                                    <input type="file" name="photos[]" id="photo-input" class="hidden" multiple accept="image/jpeg,image/png,image/jpg,image/webp" required onchange="previewSelectedPhotos(this)">
                                </label>
                            </div>

                            @error('photos')
                                <span class="text-xs font-semibold text-red-500 mt-1 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>{{ $message }}</span>
                            @enderror
                            @error('photos.*')
                                <span class="text-xs font-semibold text-red-500 mt-1 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>{{ $message }}</span>
                            @enderror

                            <div id="live-preview-grid" class="grid grid-cols-3 gap-2 hidden pt-3 border-t border-slate-100">
                                </div>
                        </div>

                        <div class="rounded-2xl bg-blue-50 border border-blue-100 p-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                                    ✓
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">
                                        Asset pipeline staging notice
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        Your property configuration will deploy live right after submission.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center gap-3 pt-2">
                            <a href="{{ route('landlord.listings.index') }}" class="w-full sm:w-1/3 h-12 rounded-2xl border border-slate-200 bg-white/80 backdrop-blur hover:bg-slate-50 font-semibold shadow-sm transition-all flex items-center justify-center text-slate-700">
                                Cancel
                            </a>
                            <button type="submit" class="w-full sm:w-2/3 h-12 rounded-2xl bg-gradient-to-r from-blue-600 via-blue-500 to-indigo-600 text-white font-extrabold hover:shadow-xl hover:shadow-blue-500/25 hover:-translate-y-0.5 transition-all duration-300">
                                Create Listing
                            </button>
                        </div>

                    </div>
                </div> </div>
        </form>
    </div>
</div>

<script>
function previewSelectedPhotos(input) {
    const previewGrid = document.getElementById('live-preview-grid');
    const labelText = document.getElementById('upload-label');
    
    previewGrid.innerHTML = ''; 
    
    if (input.files && input.files.length > 0) {
        previewGrid.classList.remove('hidden');
        labelText.textContent = input.files.length === 1 
            ? '1 Photo Asset Staged' 
            : `${input.files.length} Photo Assets Staged`;
        labelText.classList.add('text-blue-600');
        
        Array.from(input.files).forEach(file => {
            if (!file.type.startsWith('image/')) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const wrapper = document.createElement('div');
                wrapper.className = "relative aspect-square rounded-xl overflow-hidden bg-slate-100 border border-slate-200 shadow-md ring-2 ring-blue-500/10";
                
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = "w-full h-full object-cover";
                
                wrapper.appendChild(img);
                previewGrid.appendChild(wrapper);
            }
            reader.readAsDataURL(file);
        });
    } else {
        previewGrid.classList.add('hidden');
        labelText.textContent = 'Select Photos to Upload';
        labelText.classList.remove('text-blue-600');
    }
}
</script>
@endsection