<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\Landlord\AnalyticsController as LandlordAnalyticsController;
use App\Http\Controllers\Api\Landlord\DashboardController as LandlordDashboardController;
use App\Http\Controllers\Api\Landlord\OccupancyController as LandlordOccupancyController;
use App\Http\Controllers\Api\Landlord\PaymentController as LandlordPaymentController;
use App\Http\Controllers\Api\Landlord\PayoutController as LandlordPayoutController;
use App\Http\Controllers\Api\Landlord\ProfileController as LandlordProfileController;
use App\Http\Controllers\Api\Landlord\PropertyController as LandlordPropertyController;
use App\Http\Controllers\Api\Landlord\PropertyWriteController as LandlordPropertyWriteController;
use App\Http\Controllers\Api\Landlord\ReservationController as LandlordReservationController;
use App\Http\Controllers\Api\Landlord\ReviewController as LandlordReviewController;
use App\Http\Controllers\Api\Landlord\TenancyController as LandlordTenancyController;
use App\Http\Controllers\Api\Landlord\TenantRatingController;
use App\Http\Controllers\Api\Landlord\UnitController as LandlordUnitController;
use App\Http\Controllers\Api\Landlord\UnitWriteController as LandlordUnitWriteController;
use App\Http\Controllers\Api\Landlord\WalkInTenantController;
use App\Http\Controllers\Api\HandoverController;
use App\Http\Controllers\Api\Tenant\AgreementController;
use App\Http\Controllers\Api\Tenant\PaymentController as TenantPaymentController;
use App\Http\Controllers\Api\Tenant\ReservationController as TenantReservationController;
use App\Http\Controllers\Api\Tenant\ReviewController;
use App\Http\Controllers\Api\Tenant\TenancyController as TenantTenancyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// ─── Auth ────────────────────────────────────────────────────
// Credential-accepting endpoints are throttled hardest: unlike the web login
// they face a public app binary, so the attacker already has a valid client.
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('/webview-ticket', [AuthController::class, 'webviewTicket'])->middleware('auth:sanctum');

    // Mobile-ready: verifies a token a native Google/Facebook SDK produced
    // on-device and exchanges it for a Sanctum token. Unused until the
    // mobile app exists.
    Route::post('/{provider}/token', [SocialiteController::class, 'verifyToken'])
        ->whereIn('provider', ['google', 'facebook'])
        ->middleware('throttle:6,1');
});

// ─── Broadcasting auth (Sanctum) ─────────────────────────────
// The framework's own /broadcasting/auth is registered by withRouting(channels:)
// on the *web* middleware stack, so a Bearer token cannot authorize a private
// channel — every private subscription from the mobile app would fail silently.
// This is the token-guarded twin; routes/channels.php is shared by both.
Route::match(['get', 'post'], '/broadcasting/auth', fn (Request $request) => Broadcast::auth($request))
    ->middleware(['auth:sanctum', 'active']);

// ─── Public browse ───────────────────────────────────────────
Route::get('/properties', [PropertyController::class, 'index']);
Route::get('/properties/{property}', [PropertyController::class, 'show']);

// Public landlord profile (visibility enforced in controller)
Route::get('/landlord/{user}/profile', [LandlordProfileController::class, 'show'])
    ->whereNumber('user');

// ─── Authenticated ───────────────────────────────────────────
Route::middleware(['auth:sanctum', 'active', 'throttle:60,1'])->group(function () {

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword']);
    Route::patch('/profile/push-token', [ProfileController::class, 'updatePushToken']);

    // Conversations & messages
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);
    Route::post('/conversations/{conversation}/resolve', [ConversationController::class, 'resolve']);

    // Filing a report is unscoped (either role reports a listing or a
    // user), matching the web `reports.store` route. "My reports" is
    // tenant-only, matching `tenant.reports.index` — see below.
    Route::post('/reports', [ReportController::class, 'store']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

    // Key handover scheduling — symmetric (either party proposes, the other
    // confirms), so it sits outside both role middlewares below, same as the
    // web routes. Gate::authorize('scheduleHandover', ...) does the real check.
    Route::post('/reservations/{reservation}/handover/propose', [HandoverController::class, 'propose']);
    Route::post('/reservations/{reservation}/handover/confirm', [HandoverController::class, 'confirm']);

    // ── Tenant ──────────────────────────────────────────────
    Route::middleware('tenant')->group(function () {
        Route::get('/favorites', [FavoriteController::class, 'index']);
        Route::post('/favorites/{propertyId}/toggle', [FavoriteController::class, 'toggle'])
            ->whereNumber('propertyId');

        Route::get('/tenant/reservations', [TenantReservationController::class, 'index']);
        Route::post('/tenant/reservations', [TenantReservationController::class, 'store']);
        Route::patch('/tenant/reservations/{reservation}/cancel', [TenantReservationController::class, 'cancel']);

        // Agreement + escrow — mirrors Tenant\AgreementController.
        Route::get('/tenant/reservations/{reservation}/agreement', [AgreementController::class, 'show']);
        Route::post('/tenant/reservations/{reservation}/agreement/sign', [AgreementController::class, 'sign']);
        Route::post('/tenant/reservations/{reservation}/confirm-move-in', [AgreementController::class, 'confirmMoveIn']);
        Route::post('/tenant/reservations/{reservation}/dispute-move-in', [AgreementController::class, 'disputeMoveIn']);

        // Initial payment — WebView opens checkout_url, app intercepts the
        // return and calls reconcile instead of letting PayMongo's page load.
        Route::post('/tenant/reservations/{reservation}/pay', [TenantPaymentController::class, 'pay']);
        Route::post('/tenant/reservations/{reservation}/payment/reconcile', [TenantPaymentController::class, 'reconcile']);

        // Rent ledger + rent payment
        Route::get('/tenant/tenancy/{reservation}', [TenantTenancyController::class, 'show']);
        Route::post('/tenant/tenancy/{reservation}/pay-rent', [TenantPaymentController::class, 'payRent']);
        Route::post('/tenant/tenancy/{reservation}/payment/reconcile', [TenantPaymentController::class, 'reconcileRent']);

        Route::post('/reviews', [ReviewController::class, 'store']);

        Route::get('/tenant/reports', [ReportController::class, 'index']);
    });

    // ── Landlord ────────────────────────────────────────────
    Route::middleware('landlord')->prefix('landlord')->group(function () {
        Route::get('/dashboard', [LandlordDashboardController::class, 'index']);

        Route::get('/profile/me', [LandlordProfileController::class, 'me']);
        Route::patch('/profile/me', [LandlordProfileController::class, 'update']);

        Route::get('/properties', [LandlordPropertyController::class, 'index']);
        Route::get('/properties/{property}', [LandlordPropertyController::class, 'show']);
        Route::get('/properties/{property}/units', [LandlordUnitController::class, 'index']);

        // Property + unit CRUD — mirrors the shared (unnamespaced) web
        // PropertyController and Landlord\PropertyUnitController, incl. the
        // >=3 live-capture photo rule on unit creation (ARCHITECTURE.md).
        Route::post('/properties', [LandlordPropertyWriteController::class, 'store']);
        Route::match(['put', 'patch'], '/properties/{property}', [LandlordPropertyWriteController::class, 'update']);
        Route::delete('/properties/{property}', [LandlordPropertyWriteController::class, 'destroy']);
        Route::delete('/properties/{property}/media/{media}', [LandlordPropertyWriteController::class, 'destroyMedia']);

        Route::post('/properties/{property}/units', [LandlordUnitWriteController::class, 'store']);
        Route::match(['put', 'patch'], '/properties/{property}/units/{unit}', [LandlordUnitWriteController::class, 'update']);
        Route::delete('/properties/{property}/units/{unit}', [LandlordUnitWriteController::class, 'destroy']);
        Route::delete('/properties/{property}/units/{unit}/media/{media}', [LandlordUnitWriteController::class, 'destroyMedia']);

        Route::get('/reservations', [LandlordReservationController::class, 'index']);
        Route::patch('/reservations/{reservation}/advance-negotiation', [LandlordReservationController::class, 'advanceToNegotiation']);
        Route::patch('/reservations/{reservation}/advance-agreement', [LandlordReservationController::class, 'advanceToPendingAgreement']);
        Route::patch('/reservations/{reservation}/reject', [LandlordReservationController::class, 'reject']);
        Route::patch('/reservations/{reservation}/cancel', [LandlordReservationController::class, 'cancel']);
        Route::post('/reservations/{reservation}/turned-over', [LandlordReservationController::class, 'markTurnedOver']);

        Route::get('/reservations/{reservation}/rate-tenant', [TenantRatingController::class, 'show']);
        Route::post('/reservations/{reservation}/rate-tenant', [TenantRatingController::class, 'store']);

        Route::post('/tenants/walk-in', [WalkInTenantController::class, 'store']);

        Route::get('/tenancies/{reservation}', [LandlordTenancyController::class, 'show']);
        Route::post('/tenancies/{reservation}/end', [LandlordTenancyController::class, 'endTenancy']);
        Route::post('/tenancies/{reservation}/remind', [LandlordTenancyController::class, 'remind'])
            ->middleware('throttle:10,1');
        Route::post('/tenancies/{reservation}/payments', [LandlordPaymentController::class, 'store']);

        Route::get('/payments', [LandlordPaymentController::class, 'index']);
        Route::get('/payouts', [LandlordPayoutController::class, 'index']);
        Route::get('/occupancy', [LandlordOccupancyController::class, 'index']);
        Route::get('/analytics', [LandlordAnalyticsController::class, 'index']);

        Route::get('/reviews', [LandlordReviewController::class, 'index']);
        Route::patch('/reviews/{review}/reply', [LandlordReviewController::class, 'reply']);
    });
});
