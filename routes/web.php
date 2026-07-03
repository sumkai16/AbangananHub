<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Tenant\DashboardController as TenantDashboardController;
use App\Http\Controllers\Tenant\FavoriteController;
use App\Http\Controllers\Tenant\ReservationController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\Landlord\PropertyUnitController;
use App\Http\Controllers\Landlord\PropertyController as LandlordPropertyController;
use App\Http\Controllers\Admin\PropertyUnitController as AdminPropertyUnitController;
use App\Http\Controllers\Landlord\ListingController as LandlordListingController;
use App\Http\Controllers\Admin\ListingController;
use App\Http\Controllers\Admin\VerificationController as AdminVerificationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\ConversationController as AdminConversationController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Landlord\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\AgreementController;
use App\Http\Controllers\Tenant\PaymentController;
use App\Http\Controllers\PayMongoWebhookController;

Route::get('/', [PropertyController::class, 'index'])->name('home');
Route::get('/about', fn() => view('about'))->name('about');

Route::get('/dashboard', [TenantDashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Global Authenticated Routes Group
Route::middleware('auth')->group(function () {
Route::post('/conversations/{conversation}/resolve', [ConversationController::class, 'resolve'])->name('conversations.resolve');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Landlord verification — open to any authenticated user, no role gate
    Route::get('/landlord/apply', [VerificationController::class, 'create'])->name('landlord.verification.create');
    Route::post('/landlord/apply', [VerificationController::class, 'store'])->name('landlord.verification.store');
    Route::get('/landlord/verification', [VerificationController::class, 'show'])->name('landlord.verification.show');
    Route::get('/verifications/{verification}/document', [VerificationController::class, 'download'])->name('verifications.document');

    // Landlord-only routes (property create/edit/delete — no prefix, uses /properties URIs)
    Route::middleware('landlord')->group(function () {
        Route::resource('properties', PropertyController::class)->only(['create', 'store', 'edit', 'update', 'destroy']);
        Route::delete('/properties/{property}/media/{media}', [PropertyController::class, 'destroyMedia'])->name('properties.media.destroy');
    });

    // Tenant-accessible routes
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favorites/{propertyId}/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

    // Tenant-specific routes
    Route::middleware('tenant')->group(function () {
        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');

        Route::get('/reservations/{reservation}/agreement', [AgreementController::class, 'show'])->name('agreements.show');
        Route::post('/reservations/{reservation}/agreement/sign', [AgreementController::class, 'sign'])->name('agreements.sign');
        Route::post('/reservations/{reservation}/pay', [PaymentController::class, 'createCheckoutSession'])->name('payments.checkout');
        Route::get('/reservations/{reservation}/payment-success', [PaymentController::class, 'success'])->name('payments.success');
    });

    // Landlord-specific prefix routes
    Route::middleware('landlord')->prefix('landlord')->name('landlord.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        // Property management
        Route::get('/properties', [LandlordPropertyController::class, 'index'])->name('properties.index');
        Route::get('/properties/{property}', [LandlordPropertyController::class, 'show'])->name('properties.show');

        // Listings (legacy — keep until confirmed removable)
        Route::get('/listings', [LandlordListingController::class, 'index'])->name('listings.index');

      // Reservations
        Route::get('/reservations', [App\Http\Controllers\Landlord\ReservationController::class, 'index'])->name('reservations.index');
        Route::patch('/reservations/{reservation}/reject', [App\Http\Controllers\Landlord\ReservationController::class, 'reject'])->name('reservations.reject');
        Route::patch('/reservations/{reservation}/cancel', [App\Http\Controllers\Landlord\ReservationController::class, 'cancel'])->name('reservations.cancel');
        Route::patch('/reservations/{reservation}/advance-negotiation', [App\Http\Controllers\Landlord\ReservationController::class, 'advanceToNegotiation'])->name('reservations.advanceNegotiation');
        Route::patch('/reservations/{reservation}/advance-agreement', [App\Http\Controllers\Landlord\ReservationController::class, 'advanceToPendingAgreement'])->name('reservations.advanceAgreement');

        // Units
        Route::resource('properties.units', PropertyUnitController::class);
        Route::delete('/properties/{property}/units/{unit}/media/{media}', [PropertyUnitController::class, 'destroyMedia'])->name('properties.units.media.destroy');

    });

    // Admin-specific routes
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/listings/approval', [ListingController::class, 'approval'])->name('listings.approval');
        Route::post('/listings/{property_id}/approve', [ListingController::class, 'approve'])->name('listings.approve');
        Route::post('/listings/{property_id}/reject', [ListingController::class, 'reject'])->name('listings.reject');

        Route::get('/verifications', [AdminVerificationController::class, 'index'])->name('verifications.index');
        Route::get('/verifications/{verification}', [AdminVerificationController::class, 'show'])->name('verifications.show');
        Route::post('/verifications/{verification}/approve', [AdminVerificationController::class, 'approve'])->name('verifications.approve');
        Route::post('/verifications/{verification}/reject', [AdminVerificationController::class, 'reject'])->name('verifications.reject');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])->name('users.updateStatus');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        Route::get('/reservations', [AdminReservationController::class, 'index'])->name('reservations.index');
        Route::get('/reservations/{reservation}', [AdminReservationController::class, 'show'])->name('reservations.show');
        Route::patch('/reservations/{reservation}/cancel', [AdminReservationController::class, 'forceCancel'])->name('reservations.forceCancel');
        Route::patch('/reservations/{reservation}/reject', [AdminReservationController::class, 'forceReject'])->name('reservations.forceReject');

        Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
        Route::delete('/reviews/{review}', [AdminReviewController::class, 'destroy'])->name('reviews.destroy');

        Route::get('/conversations', [AdminConversationController::class, 'index'])->name('conversations.index');
        Route::get('/conversations/{conversation}', [AdminConversationController::class, 'show'])->name('conversations.show');

        Route::get('/units', [AdminPropertyUnitController::class, 'index'])->name('units.index');
        Route::get('/properties/{property}/units/{unit}', [AdminPropertyUnitController::class, 'show'])->name('units.show');
        Route::post('/properties/{property}/units/{unit}/approve', [AdminPropertyUnitController::class, 'approve'])->name('units.approve');
        Route::post('/properties/{property}/units/{unit}/reject', [AdminPropertyUnitController::class, 'reject'])->name('units.reject');
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
// PayMongo webhook — no auth, CSRF excluded in bootstrap/app.php
Route::post('/webhooks/paymongo', [PayMongoWebhookController::class, 'handle'])->name('webhooks.paymongo');
require __DIR__.'/auth.php';