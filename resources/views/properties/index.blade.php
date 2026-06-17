<x-app-layout>
    <div style="max-width:1280px;margin:40px auto;padding:0 40px;">
        <h1 style="font-size:24px;font-weight:800;color:#1A1A2E;margin-bottom:8px;">Browse Properties</h1>
        <p style="color:#9AA0AB;margin-bottom:24px;">Find verified rentals across Cebu.</p>

        @if($properties->isEmpty())
            <div style="text-align:center;padding:60px 24px;border:2px dashed #E8ECF0;border-radius:18px;background:#F9FBFF;">
                <p style="font-size:15px;font-weight:700;color:#1A1A2E;">No properties found.</p>
                <p style="color:#9AA0AB;margin-top:6px;">Try adjusting your search filters.</p>
            </div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;">
                @foreach($properties as $property)
                    <a href="{{ route('properties.show', $property) }}"
                       style="background:#fff;border:1px solid #EAECF0;border-radius:16px;padding:20px;text-decoration:none;color:#1A1A2E;display:block;transition:box-shadow 0.2s;">
                        <div style="font-size:16px;font-weight:700;margin-bottom:6px;">{{ $property->title }}</div>
                        <div style="font-size:13px;color:#9AA0AB;">{{ $property->property_type }} · {{ $property->address }}</div>
                        <div style="font-size:15px;font-weight:800;color:#286CD2;margin-top:10px;">₱{{ number_format($property->rental_fee) }}/mo</div>
                    </a>
                @endforeach
            </div>
            <div style="margin-top:24px;">{{ $properties->links() }}</div>
        @endif
    </div>
</x-app-layout>
