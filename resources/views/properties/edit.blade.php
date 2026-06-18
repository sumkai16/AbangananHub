@extends('layouts.app')

@section('hide_search', true) {{-- This line intercepts and hides the navigation search block --}}

@section('content')
<!-- 1. Upgrade the page background -->
<div class="relative min-h-screen overflow-hidden bg-slate-50 py-12">

    <div class="absolute inset-0 -z-10">
        <div class="absolute top-0 right-0 h-[500px] w-[500px] rounded-full bg-blue-100/40 blur-3xl"></div>
        <div class="absolute bottom-0 left-0 h-[400px] w-[400px] rounded-full bg-indigo-100/30 blur-3xl"></div>
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(59,130,246,0.08),_transparent_40%)]"></div>
    </div>

    <!-- 2. Increase content width -->
    <div class="max-w-7xl mx-auto px-6 lg:px-8">
        
        <!-- Header Navigation Bar -->
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
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-3xl font-black text-slate-900 tracking-tight leading-none sm:mt-0.5">Edit Property Listing</h1>
                        <p class="text-sm text-slate-500 mt-2 font-medium max-w-2xl">Refine configurations, manage asset parameters, and broadcast structural updates live across your deployment portfolio.</p>
                    </div>
                </div>
            </div>
            
            <div class="flex flex-wrap items-center gap-2.5 bg-white border border-slate-200/80 px-4 py-3 rounded-2xl shadow-[0_8px_30px_rgb(0,0,0,0.02)] backdrop-blur-md self-start md:self-center">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[11px] font-black tracking-wider uppercase bg-amber-50 text-amber-700 border border-amber-200/60 shadow-sm">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                    </span>
                    Status: {{ $property->verification_status }}
                </span>
                
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[11px] font-black tracking-wider uppercase bg-blue-50 text-blue-700 border border-blue-200/60 shadow-sm">
                    <span class="h-2 w-2 rounded-full bg-blue-500"></span>
                    {{ $property->availability_status }}
                </span>
            </div>
        </div>

        <!-- Validation Dashboard -->
        @if($errors->any())
            <div class="mb-8 p-5 bg-red-50/90 border border-red-200 rounded-2xl flex gap-4 shadow-lg shadow-red-500/5 backdrop-blur-sm">
                <div class="w-9 h-9 rounded-xl bg-red-100 flex items-center justify-center shrink-0 text-red-600 shadow-sm">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <h5 class="text-[14px] font-bold text-slate-900">There are problems with your configurations:</h5>
                    <ul class="list-disc pl-4 mt-1 text-[13px] text-red-700 space-y-0.5 font-medium">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <!-- 3. Add a property summary card at the top -->
        <div class="mb-8 rounded-[28px] border border-white/70 bg-white/80 backdrop-blur-xl shadow-[0_20px_60px_-15px_rgba(15,23,42,0.08)] overflow-hidden">
            <div class="p-6 flex flex-col md:flex-row md:items-center gap-6">
                <div class="h-20 w-20 rounded-3xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white shadow-lg shadow-blue-500/20">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10.5L12 3l9 7.5V21H3V10.5z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h2 class="text-2xl font-bold text-slate-900">
                        {{ $property->title }}
                    </h2>
                    <p class="text-slate-500 mt-1">
                        {{ $property->address }}
                    </p>
                    <div class="flex flex-wrap gap-2 mt-4">
                        <span class="px-3 py-1 rounded-full bg-blue-50 text-blue-700 text-sm font-semibold">
                            {{ $property->property_type }}
                        </span>
                        <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-sm font-semibold">
                            {{ $property->occupancy_limit }} occupants
                        </span>
                        <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-sm font-semibold">
                            ₱{{ number_format($property->rental_fee) }}/month
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Split View Layout Form -->
        <form action="{{ route('properties.update', $property->property_id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- 4. Make the form cards look premium (Core Info Panel) -->
                <div class="lg:col-span-7 bg-white/80 backdrop-blur-xl border border-white/70 rounded-[28px] p-8 shadow-[0_20px_60px_-15px_rgba(15,23,42,0.08)] space-y-6">
                    
                    <!-- 5. Improve section headers -->
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                        <div class="h-10 w-10 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-900">Core Information</h3>
                            <p class="text-sm text-slate-500">Manage the essential details of your property.</p>
                        </div>
                    </div>

                    <!-- 6. Modernize inputs applied below -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Property Name / Title</label>
                        <input type="text" name="title" value="{{ old('title', $property->title) }}" minlength="10" maxlength="150" class="w-full h-12 px-4 rounded-2xl border border-slate-200 bg-white/70 focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none text-[14px] font-medium text-slate-800 shadow-sm transition-all duration-300 hover:border-slate-300 focus:shadow-[0_0_0_6px_rgba(59,130,246,0.08)] focus:-translate-y-[1px]" placeholder="e.g., Cozy Modern Studio Apartment Near Downtown" required>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Classification Type</label>
                            <div class="relative">
                                <select name="property_type" class="w-full h-12 px-4 rounded-2xl border border-slate-200 bg-white/70 focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none text-[14px] font-medium text-slate-800 appearance-none shadow-sm transition-all duration-300 hover:border-slate-300 focus:shadow-[0_0_0_6px_rgba(59,130,246,0.08)] focus:-translate-y-[1px]" required>
                                    <option value="Bedspace" {{ old('property_type', $property->property_type) == 'Bedspace' ? 'selected' : '' }}>Bedspace</option>
                                    <option value="Room" {{ old('property_type', $property->property_type) == 'Room' ? 'selected' : '' }}>Room</option>
                                    <option value="Apartment" {{ old('property_type', $property->property_type) == 'Apartment' ? 'selected' : '' }}>Apartment</option>
                                    <option value="House" {{ old('property_type', $property->property_type) == 'House' ? 'selected' : '' }}>House</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-slate-400">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Monthly Fee (PHP)</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 font-bold text-[14px]">₱</span>
                                <input type="number" step="0.01" min="500" max="999999" name="rental_fee" value="{{ old('rental_fee', $property->rental_fee) }}" class="w-full h-12 pl-8 pr-4 rounded-2xl border border-slate-200 bg-white/70 focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none text-[14px] font-bold text-slate-800 shadow-sm transition-all duration-300 hover:border-slate-300 focus:shadow-[0_0_0_6px_rgba(59,130,246,0.08)] focus:-translate-y-[1px]" placeholder="0.00" required>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t border-slate-100 pt-5">
                        <div class="sm:col-span-1">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Occupancy Limit</label>
                            <input type="number" name="occupancy_limit" value="{{ old('occupancy_limit', $property->occupancy_limit) }}" min="1" max="100" class="w-full h-12 px-4 rounded-2xl border border-slate-200 bg-white/70 focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none text-[14px] font-medium text-slate-800 shadow-sm transition-all duration-300 hover:border-slate-300 focus:shadow-[0_0_0_6px_rgba(59,130,246,0.08)] focus:-translate-y-[1px]" required>
                        </div>
                        <div class="sm:col-span-1">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Latitude</label>
                            <input type="number" step="any" min="-90" max="90" name="latitude" value="{{ old('latitude', $property->latitude) }}" placeholder="10.3157" class="w-full h-12 px-4 rounded-2xl border border-slate-200 bg-white/70 focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none text-[14px] text-slate-700 shadow-sm transition-all duration-300 hover:border-slate-300 focus:shadow-[0_0_0_6px_rgba(59,130,246,0.08)] focus:-translate-y-[1px]">
                        </div>
                        <div class="sm:col-span-1">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Longitude</label>
                            <input type="number" step="any" min="-180" max="180" name="longitude" value="{{ old('longitude', $property->longitude) }}" placeholder="123.8854" class="w-full h-12 px-4 rounded-2xl border border-slate-200 bg-white/70 focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none text-[14px] text-slate-700 shadow-sm transition-all duration-300 hover:border-slate-300 focus:shadow-[0_0_0_6px_rgba(59,130,246,0.08)] focus:-translate-y-[1px]">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Complete Structural Address</label>
                        <input type="text" name="address" value="{{ old('address', $property->address) }}" minlength="10" maxlength="255" class="w-full h-12 px-4 rounded-2xl border border-slate-200 bg-white/70 focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none text-[14px] font-medium text-slate-800 shadow-sm transition-all duration-300 hover:border-slate-300 focus:shadow-[0_0_0_6px_rgba(59,130,246,0.08)] focus:-translate-y-[1px]" placeholder="Street name, Barangay, City, Province" required>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-400 mb-2">Property Description Portfolio</label>
                        <textarea name="description" rows="6" minlength="20" maxlength="3000" class="w-full p-4 rounded-2xl border border-slate-200 bg-white/70 focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 outline-none text-[14px] font-medium text-slate-800 leading-relaxed shadow-sm transition-all duration-300 hover:border-slate-300 focus:shadow-[0_0_0_6px_rgba(59,130,246,0.08)] focus:-translate-y-[1px]" placeholder="Describe layout features, rules, structural terms, environment perks..." required>{{ old('description', $property->description) }}</textarea>
                    </div>
                </div>

                <!-- 8. Make the save panel sticky -->
                <div class="lg:col-span-5">
                    <div class="sticky top-8 space-y-6">
                        
                        <!-- 4. Make the form cards look premium (Active Gallery Card) -->
                        <div class="bg-white/80 backdrop-blur-xl border border-white/70 rounded-[28px] p-6 shadow-[0_20px_60px_-15px_rgba(15,23,42,0.08)]">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3.5 mb-4">
                                <div class="flex items-center gap-2">
                                    <div class="h-8 w-8 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                    <h3 class="text-sm font-bold text-slate-900">Active Showcase Gallery</h3>
                                </div>
                                <span class="text-[11px] font-bold tracking-wide uppercase text-blue-600 bg-blue-50 border border-blue-100 px-2.5 py-0.5 rounded-full shadow-sm">{{ $property->media->count() }} Files</span>
                            </div>
                            
                            <div class="grid grid-cols-3 gap-3">
                                @forelse($property->media as $img)
                                    <!-- 7. Transform the gallery into a showcase -->
                                    <div class="relative group aspect-square rounded-2xl overflow-hidden bg-slate-100 border border-slate-200/40 ring-0 hover:ring-4 hover:ring-blue-500/10 shadow-sm hover:shadow-md transition-all duration-300">
                                        <img src="{{ asset('storage/' . $img->media_url) }}" class="w-full h-full object-cover transition duration-500 group-hover:scale-110" alt="Gallery thumbnail">
                                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/50 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition"></div>
                                        <div class="absolute bottom-2 left-2 px-2 py-1 rounded-lg bg-white/90 text-xs font-semibold">
                                            Existing
                                        </div>
                                    </div>
                                @empty
                                    <div class="col-span-3 py-8 text-center bg-slate-50/50 border border-dashed border-slate-200 rounded-2xl">
                                        <p class="text-[13px] text-slate-400 italic">No media items configured for this listing.</p>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <!-- 4. Make the form cards look premium (Upload Card) -->
                        <div class="bg-white/80 backdrop-blur-xl border border-white/70 rounded-[28px] p-6 shadow-[0_20px_60px_-15px_rgba(15,23,42,0.08)] space-y-4">
                            <div class="flex items-center gap-2 border-b border-slate-100 pb-3">
                                <div class="h-8 w-8 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                </div>
                                <h3 class="text-sm font-bold text-slate-900">Inject Media Updates</h3>
                            </div>
                            
                            <div class="border-2 border-dashed border-slate-200/80 hover:border-blue-400 rounded-2xl p-6 bg-gradient-to-b from-slate-50/40 to-slate-50 hover:from-white hover:to-blue-50/10 text-center transition-all duration-300 group relative shadow-inner">
                                <label class="cursor-pointer block">
                                    <div class="w-12 h-12 rounded-2xl bg-white shadow-md border border-slate-100 flex items-center justify-center mx-auto mb-3 text-slate-400 group-hover:text-blue-600 group-hover:scale-110 group-hover:shadow-blue-500/5 transition-all duration-300">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 002-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                    <span id="upload-label" class="text-[14px] font-bold text-slate-800 transition-colors group-hover:text-blue-600">Upload New Photo Assets</span>
                                    <p class="text-[12px] text-slate-400 mt-1">Accepts JPEG, PNG, WEBP profiles (Max 5MB per file setup)</p>
                                    <input type="file" name="photos[]" id="photo-input" class="hidden" multiple accept="image/jpeg,image/png,image/jpg,image/webp" onchange="previewSelectedPhotos(this)">
                                </label>
                            </div>

                            <!-- Live Front-End Queue Preview Matrix Container -->
                            <div id="live-preview-grid" class="grid grid-cols-4 gap-2 hidden pt-3 border-t border-slate-100">
                                <!-- Injected dynamically via JavaScript -->
                            </div>
                        </div>

                        <!-- 10. Add a floating save indicator -->
                        <div class="rounded-2xl bg-blue-50 border border-blue-100 p-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600 font-bold">
                                    ✓
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">
                                        Changes are saved only after submission
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        Review all details before updating your listing.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- 9. Upgrade the action buttons -->
                        <div class="flex flex-col sm:flex-row items-center gap-3 pt-2">
                            <a href="{{ route('landlord.listings.index') }}" class="w-full sm:w-1/3 h-12 rounded-2xl border border-slate-200 bg-white/80 backdrop-blur hover:bg-slate-50 font-semibold shadow-sm transition-all flex items-center justify-center text-slate-700">
                                Cancel
                            </a>
                            <button type="submit" class="w-full sm:w-2/3 h-12 rounded-2xl bg-gradient-to-r from-blue-600 via-blue-500 to-indigo-600 text-white font-extrabold hover:shadow-xl hover:shadow-blue-500/25 hover:-translate-y-0.5 transition-all duration-300">
                                Save Structural Changes
                            </button>
                        </div>

                    </div>
                </div> <!-- Closing sticky container alignment anchor -->

            </div>
        </form>
    </div>
</div>

<!-- JavaScript Engine for Live Queued Media Previews -->
<script>
function previewSelectedPhotos(input) {
    const previewGrid = document.getElementById('live-preview-grid');
    const labelText = document.getElementById('upload-label');
    
    previewGrid.innerHTML = ''; 
    
    if (input.files && input.files.length > 0) {
        previewGrid.classList.remove('hidden');
        labelText.textContent = `${input.files.length} Photo Assets Staged`;
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
        labelText.textContent = 'Upload New Photo Assets';
        labelText.classList.remove('text-blue-600');
    }
}
</script>
@endsection