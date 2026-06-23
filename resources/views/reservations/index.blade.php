@extends('layouts.app', ['searchBar' => false])

@section('content')
    <div class="max-w-[900px] mx-auto px-6 py-10">

        <div class="mb-8">
            <h1 class="text-[24px] font-extrabold text-[#1A1A2E] mb-1">My Reservations</h1>
            <p class="text-gray-500">All your current and past bookings.</p>
        </div>

        @if(session('success'))
            <div
                class="mb-6 px-4 py-3 rounded-lg bg-emerald-50 text-emerald-800 text-[14px] font-medium border border-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 text-red-800 text-[14px] font-medium border border-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        @if($reservations->isEmpty())
            <x-empty-state title="No reservations yet" message="Once you reserve a property, it will show up here."
                href="{{ route('properties.index') }}" cta="Browse listings" />
        @else
            <div class="flex flex-col gap-4">
                @foreach($reservations as $reservation)
                    <x-reservation-card :reservation="$reservation" :show-actions="true" />
                @endforeach
            </div>

            <div class="mt-8">
                {{ $reservations->links() }}
            </div>
        @endif

    </div>
@endsection