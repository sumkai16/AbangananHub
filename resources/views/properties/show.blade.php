<x-app-layout>
    <div style="max-width:900px;margin:40px auto;padding:0 40px;">
        <a href="{{ route('properties.index') }}" style="font-size:13px;color:#286CD2;text-decoration:none;">← Back to listings</a>
        <h1 style="font-size:24px;font-weight:800;color:#1A1A2E;margin:16px 0 4px;">{{ $property->title }}</h1>
        <p style="color:#9AA0AB;margin-bottom:24px;">{{ $property->property_type }} · {{ $property->address }}</p>
        <div style="font-size:22px;font-weight:900;color:#286CD2;margin-bottom:20px;">₱{{ number_format($property->rental_fee) }}/mo</div>
        <p style="color:#374151;line-height:1.7;">{{ $property->description }}</p>
    </div>
</x-app-layout>
