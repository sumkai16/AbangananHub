@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-[#15803D]']) }}>
        {{ $status }}
    </div>
@endif
