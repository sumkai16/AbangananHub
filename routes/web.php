<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantDashboardController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\VerificationController;

// Aliased to prevent naming collisions between roles
use App\Http\Controllers\Landlord\ListingController as LandlordListingController;
use App\Http\Controllers\Admin\ListingController as AdminListingController;
use App\Http\Controllers\Admin\VerificationController as AdminVerificationController;

use Illuminate\Support\Facades\Route;

Route::get('/', [PropertyController::class, 'index'])->name('home');

Route::get('/dashboard', [TenantDashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Global Authenticated Routes Group
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Landlord verification — open to any authenticated user, no role gate
    // (gating this with 'landlord' middleware would be a chicken-and-egg problem)
    Route::get('/landlord/apply', [VerificationController::class, 'create'])->name('landlord.verification.create');
    Route::post('/landlord/apply', [VerificationController::class, 'store'])->name('landlord.verification.store');
    Route::get('/landlord/verification', [VerificationController::class, 'show'])->name('landlord.verification.show');
    Route::get('/verifications/{verification}/document', [VerificationController::class, 'download'])->name('verifications.document');

    // Landlord-only routes (Placed BEFORE wildcards to prevent 404 collision)
    Route::middleware('landlord')->group(function () {
        Route::resource('properties', PropertyController::class)->except(['index', 'show']);
        Route::get('/landlord/listings', [LandlordListingController::class, 'index'])->name('landlord.listings.index');
        Route::delete('/properties/{property}/media/{media}', [PropertyController::class, 'destroyMedia'])->name('properties.media.destroy');
    });

    // Tenant-accessible routes
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favorites/{propertyId}/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

    // Tenant-specific routes
    Route::middleware('tenant')->group(function () {
        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::post('/properties/{property}/reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    });

    // Landlord-specific prefix routes
    Route::middleware('landlord')->prefix('landlord')->name('landlord.')->group(function () {
        Route::get('/reservations', [App\Http\Controllers\Landlord\ReservationController::class, 'index'])->name('reservations.index');
        Route::patch('/reservations/{reservation}/approve', [App\Http\Controllers\Landlord\ReservationController::class, 'approve'])->name('reservations.approve');
        Route::patch('/reservations/{reservation}/reject', [App\Http\Controllers\Landlord\ReservationController::class, 'reject'])->name('reservations.reject');
    });

    // Admin-specific routes
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/listings/approval', [AdminListingController::class, 'approval'])->name('listings.approval');
        Route::post('/listings/{property_id}/approve', [AdminListingController::class, 'approve'])->name('listings.approve');
        Route::post('/listings/{property_id}/reject', [AdminListingController::class, 'reject'])->name('listings.reject');

        Route::get('/verifications', [AdminVerificationController::class, 'index'])->name('verifications.index');
        Route::get('/verifications/{verification}', [AdminVerificationController::class, 'show'])->name('verifications.show');
        Route::post('/verifications/{verification}/approve', [AdminVerificationController::class, 'approve'])->name('verifications.approve');
        Route::post('/verifications/{verification}/reject', [AdminVerificationController::class, 'reject'])->name('verifications.reject');
    });

    // Conversations and messages
    Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])->name('messages.store');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
});

// Publicly accessible property routes
Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');

require __DIR__.'/auth.php';