<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Landlord sign-up. Same account form; submitting it continues into the ID and face verification wizard.
     */
    public function createLandlord(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return view('auth.register-landlord');
        }

        // Signed in: this same address continues the flow with step 3, the ID and face check. Approved
        // landlords and pending applicants are sent where they belong by the verification controller.
        if ($user->hasRole('Landlord') || $user->hasRole('Admin')) {
            return redirect($user->homeRoute());
        }

        return app(\App\Http\Controllers\VerificationController::class)->create(signupFlow: true);
    }

    /**
     * Live check for the sign-up forms: is this email still free? `available` is null for anything that is
     * not a plausible email, so the page says nothing until the address is complete.
     */
    public function emailAvailable(Request $request): JsonResponse
    {
        $email = $request->query('email');

        if (! is_string($email) || strlen($email) > 255 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return response()->json(['available' => null])->header('Cache-Control', 'no-store');
        }

        return response()
            ->json(['available' => ! User::where('email', mb_strtolower($email))->exists()])
            ->header('Cache-Control', 'no-store');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'first_name'     => ['required', 'string', 'max:255'],
            'last_name'      => ['required', 'string', 'max:255'],
            'email'          => ['bail', 'required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'contact_number' => ['required', 'string', 'max:20', 'regex:/^[0-9+()\s-]{7,20}$/'],
            'password'       => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'first_name'     => $request->first_name,
            'last_name'      => $request->last_name,
            'email'          => $request->email,
            'contact_number' => $request->contact_number,
            'password'       => Hash::make($request->password),
        ]);

        // Every self-registered account starts as a Tenant, matching
        // Api\AuthController::register (mobile). Landlord role requires a
        // separate verification application on top of this.
        $user->assignRole('Tenant');

        event(new Registered($user));

        Auth::login($user);

        // A landlord sign-up continues into the verification wizard; the account stays a Tenant until an admin approves.
        $destination = $request->boolean('as_landlord') ? route('register.landlord') : $user->homeRoute();

        if ($request->wantsJson()) {
            return response()->json([
                'redirect_url' => $destination,
            ]);
        }

        return redirect($destination);
    }
}
