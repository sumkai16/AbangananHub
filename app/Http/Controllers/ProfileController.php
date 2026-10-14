<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\ImageUploader;
use App\Services\RentLedger;
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
     * Set the profile photo from the avatar pencil on Account Settings. Works for every role,
     * so the page needs no trip to a role-specific edit screen. Cropped square on the face,
     * same as the tenant and landlord profile forms.
     */
    public function updatePhoto(Request $request, ImageUploader $uploader): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'photo.image' => 'Choose a JPG, PNG or WebP image.',
            'photo.mimes' => 'Choose a JPG, PNG or WebP image.',
            'photo.max' => 'That photo is over 5 MB. Choose a smaller one.',
        ]);

        $uploaded = $uploader->upload($request->file('photo'), 'abanganganhub/profile-pictures', [
            'transformation' => ['width' => 400, 'height' => 400, 'crop' => 'fill', 'gravity' => 'face'],
        ]);

        $request->user()->update(['profile_picture' => $uploaded['url']]);

        return Redirect::route('profile.edit')->with('success', 'Profile photo updated.');
    }

    /**
     * Delete the user's account.
     *
     * Every FK back to users.user_id is onDelete('cascade') (see
     * context/RULES.md — "Hard deletes on User cascade"), so an unconditional
     * $user->delete() silently destroys reservations, payments/rent history,
     * properties, conversations, and reviews with no DB-level safety net.
     * Admin\UserController::destroy already guards the equivalent admin-driven
     * delete; this mirrors that guard on the self-service path, which never
     * had it. A genuinely clean account still deletes.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($blocker = $this->activeObligation($user)) {
            return back()->with('error', $blocker);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * The first reason this account can't be hard-deleted, or null if there
     * isn't one. Named, not generic — a refusal with no reason is a dead end.
     */
    private function activeObligation($user): ?string
    {
        $liveReservations = $user->reservations()
            ->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)
            ->with(['property', 'unit', 'payments', 'depositCharges'])
            ->get();

        $occupied = $liveReservations->where('rental_status', 'Occupied');

        if ($occupied->isNotEmpty()) {
            $outstanding = $occupied->sum(
                fn (Reservation $r) => RentLedger::for($r)->summary()['outstanding']
            );

            return $outstanding > 0
                ? sprintf(
                    'You have an occupied tenancy and ₱%s in unpaid rent. Settle your balance and end the tenancy before deleting your account.',
                    number_format($outstanding, 2)
                )
                : 'You have an occupied tenancy. End it before deleting your account.';
        }

        if ($liveReservations->isNotEmpty()) {
            return 'You have an active inquiry or reservation in progress. Cancel it before deleting your account.';
        }

        // payments.reservation_id cascades, so deleting the account would delete these rows too. A Cancelled or
        // Completed tenancy is not "live" (checked above) but can still hold escrowed money, or rent the landlord
        // has not been paid out yet. That money has to be settled first, not erased with the account.
        $unsettled = Payment::whereHas('reservation', fn ($q) => $q->where('tenant_id', $user->user_id))
            ->where(fn ($q) => $q->where('status', 'Held')->orWhere('payout_status', 'Pending Payout'))
            ->exists();

        if ($unsettled) {
            return 'You have payments that are still held by AbangananHub or waiting to be paid out to a landlord, so your account cannot be deleted yet. Contact support and we will settle them first.';
        }

        if ($user->properties()->exists()) {
            return 'You have listed properties on record and cannot be deleted — remove your listings first, or contact support.';
        }

        if ($user->reviews()->exists()) {
            return 'You have written reviews on record and cannot be deleted, to preserve that history for other tenants.';
        }

        return null;
    }
}
