<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Redirect to the role-specific profile page.
     */
    public function show(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasRole('Admin')) {
            return redirect()->route('admin.profile.edit');
        }

        if ($user->hasRole('Landlord')) {
            return redirect()->route('landlord.profile.me');
        }

        if ($user->hasRole('Tenant')) {
            return redirect()->route('tenant.profile.show');
        }

        // New accounts start with no role — fall back to account settings.
        return redirect()->route('profile.edit');
    }

    /**
     * Display the user's account settings form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('success', 'Your profile has been updated.');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
