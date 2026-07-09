<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\Landlord\DashboardController as LandlordDashboardController;
use App\Http\Controllers\Api\Landlord\ProfileController as LandlordProfileController;
use App\Http\Controllers\Api\Landlord\PropertyController as LandlordPropertyController;
use App\Http\Controllers\Api\Landlord\ReservationController as LandlordReservationController;
use App\Http\Controllers\Api\Landlord\TenantRatingController;
use App\Http\Controllers\Api\Landlord\UnitController as LandlordUnitController;
use App\Http\Controllers\Api\Tenant\ReservationController as TenantReservationController;
use App\Http\Controllers\Api\Tenant\ReviewController;
use Illuminate\Support\Facades\Route;

// ─── Auth ────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// ─── Public browse ───────────────────────────────────────────
Route::get('/properties', [PropertyController::class, 'index']);
Route::get('/properties/{property}', [PropertyController::class, 'show']);

// Public landlord profile (visibility enforced in controller)
Route::get('/landlord/{user}/profile', [LandlordProfileController::class, 'show'])
    ->whereNumber('user');

// ─── Authenticated ───────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::patch('/profile/password', [ProfileController::class, 'updatePassword']);

    // Conversations & messages
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

    // ── Tenant ──────────────────────────────────────────────
    Route::middleware('tenant')->group(function () {
        Route::get('/favorites', [FavoriteController::class, 'index']);
        Route::post('/favorites/{propertyId}/toggle', [FavoriteController::class, 'toggle'])
            ->whereNumber('propertyId');

        Route::get('/tenant/reservations', [TenantReservationController::class, 'index']);
        Route::post('/tenant/reservations', [TenantReservationController::class, 'store']);
        Route::patch('/tenant/reservations/{reservation}/cancel', [TenantReservationController::class, 'cancel']);

        Route::post('/reviews', [ReviewController::class, 'store']);
    });

    // ── Landlord ────────────────────────────────────────────
    Route::middleware('landlord')->prefix('landlord')->group(function () {
        Route::get('/dashboard', [LandlordDashboardController::class, 'index']);

        Route::get('/properties', [LandlordPropertyController::class, 'index']);
        Route::get('/properties/{property}', [LandlordPropertyController::class, 'show']);
        Route::get('/properties/{property}/units', [LandlordUnitController::class, 'index']);

        Route::get('/reservations', [LandlordReservationController::class, 'index']);
        Route::patch('/reservations/{reservation}/advance-negotiation', [LandlordReservationController::class, 'advanceToNegotiation']);
        Route::patch('/reservations/{reservation}/advance-agreement', [LandlordReservationController::class, 'advanceToPendingAgreement']);
        Route::patch('/reservations/{reservation}/reject', [LandlordReservationController::class, 'reject']);
        Route::patch('/reservations/{reservation}/cancel', [LandlordReservationController::class, 'cancel']);

        Route::get('/reservations/{reservation}/rate-tenant', [TenantRatingController::class, 'show']);
        Route::post('/reservations/{reservation}/rate-tenant', [TenantRatingController::class, 'store']);
    });
});
