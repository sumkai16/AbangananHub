<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;

class ListingController extends Controller
{
    public function index()
    {
        $properties = auth()->user()->properties()
            ->with(['media', 'units'])
            ->latest()
            ->get();

        return view('landlord.listings.index', compact('properties'));
    }
}