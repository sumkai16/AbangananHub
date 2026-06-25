<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantDashboardController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\MessageController;

// Aliased to prevent naming collisions between roles
use App\Http\Controllers\Landlord\ListingController as LandlordListingController;
use App\Http\Controllers\Admin\ListingController as AdminListingController;

use Illuminate\Support\Facades\Route;

Route::get('/', [PropertyController::class, 'index'])->name('home');

// Publicly accessible property routes
Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');

Route::get('/dashboard', [TenantDashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Global Authenticated Routes Group
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Landlord-only routes (Placed BEFORE wildcards to prevent 404 collision)
    Route::middleware('landlord')->group(function () {
        Route::resource('properties', PropertyController::class)->except(['index', 'show']);
        Route::get('/landlord/listings', [LandlordListingController::class, 'index'])->name('landlord.listings.index');
    });

    // Tenant-accessible routes
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favorites/{propertyId}/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    // Tenant-specific routes (Redundant 'auth' middleware stripped)
    Route::middleware('tenant')->group(function () {
        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::post('/properties/{property}/reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    });

    // Landlord-specific prefix routes (Redundant 'auth' middleware stripped)
    Route::middleware('landlord')->prefix('landlord')->name('landlord.')->group(function () {
        Route::get('/reservations', [App\Http\Controllers\Landlord\ReservationController::class, 'index'])->name('reservations.index');
        Route::patch('/reservations/{reservation}/approve', [App\Http\Controllers\Landlord\ReservationController::class, 'approve'])->name('reservations.approve');
        Route::patch('/reservations/{reservation}/reject', [App\Http\Controllers\Landlord\ReservationController::class, 'reject'])->name('reservations.reject');
    });

    // // Admin-specific routes (FIXES: RouteNotFoundException)
    // Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
    //     Route::get('/listings/approval', [AdminListingController::class, 'approval'])->name('listings.approval');
    // });

    // Conversations and messages
    Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])->name('messages.store');
    

    //notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/recent', [NotificationController::class, 'recent'])->name('notifications.recent');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
});

require __DIR__.'/auth.php';