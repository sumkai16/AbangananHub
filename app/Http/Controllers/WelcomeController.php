<?php

namespace App\Http\Controllers;

use App\Models\Property;

class WelcomeController extends Controller
{
    public function index()
    {
        $properties = Property::approved()
            ->available()
            ->latest()
            ->take(8)
            ->get();

        return view('welcome', compact('properties'));
    }
}