@extends('layouts.app')

@section('content')
<div class="max-w-[1280px] mx-auto my-10 px-6 lg:px-10">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-gray-200 pb-6 mb-8">
        <div>
            <h1 class="text-24px font-extrabold text-[#1A1A2E] tracking-tight">My Listings</h1>
            <p class="text-gray-500 text-[14px] mt-1">Manage, update, and monitor your rental properties registered on AbangananHub.</p>
        </div>
        <a href="{{ route('properties.create') }}" class="inline-flex items-center justify-center gap-2 h-11 px-6 rounded-xl bg-[#286CD2] text-white text-[14px] font-bold shadow-sm hover:bg-[#1e56b3] transition-all">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            Add New Property
        </a>
    </div>

    @if(Auth::user()->properties && Auth::user()->properties->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach(Auth::user()->properties as $property)
                <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm flex flex-col hover:shadow-md transition-shadow">
                    <div class="relative h-48 bg-gray-100">
                        @if($property->media && $property->media->first())
                            @php
                                $mediaItem = $property->media->first();
                                $resolvedPath = null;

                                // Intelligent Auto-Detection Scanner for Database Columns
                                $attributes = $mediaItem->getAttributes();
                                foreach (['file_path', 'image_path', 'path', 'url', 'link', 'image', 'filename', 'media_url'] as $key) {
                                    if (!empty($attributes[$key])) {
                                        $resolvedPath = $attributes[$key];
                                        break;
                                    }
                                }

                                // Fallback: Scan for any text string that isn't a standard database ID or timestamp
                                if (empty($resolvedPath)) {
                                    foreach ($attributes as $key => $val) {
                                        if (!in_array($key, ['id', 'property_id', 'created_at', 'updated_at', 'media_id']) && is_string($val) && strlen($val) > 3) {
                                            $resolvedPath = $val;
                                            break;
                                        }
                                    }
                                    $resolvedPath = $resolvedPath ?? '';
                                }

                                $isUrl = str_starts_with($resolvedPath, 'http://') || str_starts_with($resolvedPath, 'https://');
                            @endphp
                            
                            @if(!empty($resolvedPath))
                                <img src="{{ $isUrl ? $resolvedPath : asset('storage/' . $resolvedPath) }}" alt="Property photo" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-gray-400">No photos available</div>
                            @endif
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-400">No photos available</div>
                        @endif
                        
                        <div class="absolute top-3 left-3 flex flex-col gap-1.5">
                            @if($property->verification_status === 'Approved')
                                <span class="px-3 py-1 text-[11px] font-bold uppercase tracking-wider bg-emerald-500 text-white rounded-full shadow-sm">Approved</span>
                            @elseif($property->verification_status === 'Pending')
                                <span class="px-3 py-1 text-[11px] font-bold uppercase tracking-wider bg-amber-500 text-white rounded-full shadow-sm">Pending Approval</span>
                            @else
                                <span class="px-3 py-1 text-[11px] font-bold uppercase tracking-wider bg-red-500 text-white rounded-full shadow-sm">Rejected</span>
                            @endif
                        </div>
                    </div>

                    <div class="p-5 flex-grow flex flex-col">
                        <span class="text-[12px] font-bold text-[#286CD2] uppercase tracking-wide">{{ $property->property_type }}</span>
                        <h3 class="text-[16px] font-bold text-gray-900 mt-1 truncate">{{ $property->title }}</h3>
                        <p class="text-gray-500 text-[13px] mt-1 line-clamp-2">{{ $property->address }}</p>
                        
                        <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between">
                            <div>
                                <span class="text-[16px] font-extrabold text-[#1A1A2E]">₱{{ number_format($property->rental_fee, 2) }}</span>
                                <span class="text-[12px] text-gray-400">/ month</span>
                            </div>
                            <div class="text-[13px] text-gray-600 font-medium">
                                Max: {{ $property->occupancy_limit }} pax
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 mt-5 pt-2 border-t border-gray-100">
                            <a href="{{ route('properties.edit', $property->property_id) }}" class="flex items-center justify-center h-9 rounded-lg bg-gray-50 border border-gray-200 text-[13px] font-semibold text-gray-700 hover:bg-gray-100 transition-colors">
                                Edit Details
                            </a>
                            <form action="{{ route('properties.destroy', $property->property_id) }}" method="POST" onsubmit="return confirm('Are you sure you want to permanently delete this listing?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full flex items-center justify-center h-9 rounded-lg bg-red-50 border border-red-100 text-[13px] font-semibold text-red-600 hover:bg-red-100 transition-colors">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="bg-white border border-gray-100 rounded-2xl p-12 text-center max-w-md mx-auto shadow-sm mt-10">
            <div class="w-16 h-16 bg-blue-50 text-[#286CD2] rounded-2xl flex items-center justify-center mx-auto mb-4">
                <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <h3 class="text-[18px] font-bold text-gray-900">No properties listed yet</h3>
            <p class="text-gray-500 text-[13.5px] mt-1.5 px-4">Start earning by showcasing your space to looking tenants in Cebu.</p>
            <a href="{{ route('properties.create') }}" class="inline-flex items-center h-10 px-5 bg-[#286CD2] text-white rounded-xl text-[13px] font-bold mt-5 hover:bg-[#1e56b3] transition-colors">
                List Your First Property
            </a>
        </div>
    @endif
</div>
@endsection