<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantDashboardController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Landlord\ListingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WelcomeController;
use App\Http\Controllers\PropertyController;

Route::get('/', [WelcomeController::class, 'index']);

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
    Route::get('/properties', [PropertyController::class, 'index'])->name('properties.index');
    Route::get('/properties/{property}', [PropertyController::class, 'show'])->name('properties.show');
    
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favorites/{propertyId}/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
});

require __DIR__.'/auth.php';