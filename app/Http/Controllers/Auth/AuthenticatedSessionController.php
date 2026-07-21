<?php
namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }
    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $destination = $request->user()->homeRoute();

        if ($request->wantsJson()) {
            return response()->json([
                'redirect_url' => $destination,
            ]);
        }

        return redirect($destination);
    }
    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // Logout from all guards to fully clear auth state.
        Auth::logout();
        // Invalidate session & regenerate CSRF token.
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        // Redirect directly to your home root landing page
        return redirect('/');
    }
}