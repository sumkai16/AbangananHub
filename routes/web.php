<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantDashboardController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Landlord\ListingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\MessageController;

Route::get('/', [PropertyController::class, 'index'])->name('home');

// Publicly accessible property routes
Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');

Route::get('/dashboard', [TenantDashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Landlord-only routes (Placed BEFORE wildcards to prevent 404 collision)
    Route::middleware('landlord')->group(function () {
        Route::resource('properties', PropertyController::class)->except(['index', 'show']);
        Route::get('/landlord/listings', [ListingController::class, 'index'])->name('landlord.listings.index');
    });

    // Tenant-accessible routes

    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favorites/{propertyId}/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

 // Tenant
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/reservations', [App\Http\Controllers\ReservationController::class, 'index'])->name('reservations.index');
    Route::post('/properties/{property}/reservations', [App\Http\Controllers\ReservationController::class, 'store'])->name('reservations.store');
    Route::patch('/reservations/{reservation}/cancel', [App\Http\Controllers\ReservationController::class, 'cancel'])->name('reservations.cancel');
});

// Landlord
Route::middleware(['auth', 'landlord'])->prefix('landlord')->name('landlord.')->group(function () {
    Route::get('/reservations', [App\Http\Controllers\Landlord\ReservationController::class, 'index'])->name('reservations.index');
    Route::patch('/reservations/{reservation}/approve', [App\Http\Controllers\Landlord\ReservationController::class, 'approve'])->name('reservations.approve');
    Route::patch('/reservations/{reservation}/reject', [App\Http\Controllers\Landlord\ReservationController::class, 'reject'])->name('reservations.reject');
});

    // Conversations and messages
    Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])->name('messages.store');
});

require __DIR__.'/auth.php';