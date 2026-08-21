<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Tenant\FavoriteController;
use App\Http\Controllers\Tenant\ReservationController;
use App\Http\Controllers\Tenant\ProfileController as TenantProfileController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\HandoverController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\Landlord\PropertyUnitController;
use App\Http\Controllers\Landlord\PropertyController as LandlordPropertyController;
use App\Http\Controllers\Landlord\PropertyWizardController;
use App\Http\Controllers\Landlord\PropertyDocumentController as LandlordPropertyDocumentController;
use App\Http\Controllers\Admin\PropertyUnitController as AdminPropertyUnitController;
use App\Http\Controllers\Admin\PropertyDocumentController as AdminPropertyDocumentController;
use App\Http\Controllers\Admin\ListingController;
use App\Http\Controllers\Admin\VerificationController as AdminVerificationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\ConversationController as AdminConversationController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\PropertyCatalogueController;
use App\Http\Controllers\Admin\UnitCatalogueController;
use App\Http\Controllers\Landlord\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tenant\AgreementController;
use App\Http\Controllers\Tenant\PaymentController;
use App\Http\Controllers\PayMongoWebhookController;
use App\Http\Controllers\Admin\ReportAnalyticsController;
use App\Http\Controllers\Auth\WebviewLoginController;


Route::get('/', [PropertyController::class, 'index'])->name('home');
Route::get('/about', fn() => view('about'))->name('about');

// Global Authenticated Routes Group
Route::middleware('auth')->group(function () {
Route::post('/conversations/{conversation}/resolve', [ConversationController::class, 'resolve'])->name('conversations.resolve');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/settings', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Landlord verification — open to any authenticated user, no role gate
    Route::get('/landlord/apply', [VerificationController::class, 'create'])->name('landlord.verification.create');
    Route::post('/landlord/apply', [VerificationController::class, 'store'])->name('landlord.verification.store');
    Route::post('/landlord/apply/ocr-check', [VerificationController::class, 'ocrCheck'])->name('landlord.verification.ocrCheck');
    Route::post('/landlord/apply/send-email-link', [VerificationController::class, 'sendEmailLink'])
    ->name('landlord.verification.sendEmailLink')
    ->middleware('throttle:3,5');
    Route::get('/landlord/verification', [VerificationController::class, 'show'])->name('landlord.verification.show');
    Route::get('/landlord/apply/via-email/{user}', [VerificationController::class, 'createFromEmail'])
    ->name('landlord.verification.viaemail')
    ->middleware('signed');
    Route::get('/verifications/{verification}/document', [VerificationController::class, 'download'])->name('verifications.document');
    Route::get('/verifications/{verification}/selfie', [VerificationController::class, 'downloadSelfie'])->name('verifications.selfie');
    Route::get('/verifications/{verification}/id-back', [VerificationController::class, 'downloadIdBack'])->name('verifications.idBack');
    Route::get('/verifications/{verification}/preview/{type}', [VerificationController::class, 'preview'])->name('verifications.preview')->where('type', 'front|back|selfie');

    // Key handover scheduling — symmetric, so it sits outside both role
    // groups: either party proposes a slot and the other confirms it.
    Route::post('/reservations/{reservation}/handover/propose', [HandoverController::class, 'propose'])->name('handover.propose');
    Route::post('/reservations/{reservation}/handover/confirm', [HandoverController::class, 'confirm'])->name('handover.confirm');

    // Complaint & Reporting — open to any authenticated user, no role gate
    Route::get('/reports', [App\Http\Controllers\ReportController::class, 'create'])->name('reports.create');
    Route::post('/reports', [App\Http\Controllers\ReportController::class, 'store'])->name('reports.store');

    // Landlord-only routes (property create/edit/delete — no prefix, uses /properties URIs)
    Route::middleware('landlord')->group(function () {
        Route::resource('properties', PropertyController::class)->only(['edit', 'update', 'destroy']);
        Route::delete('/properties/{property}/media/{media}', [PropertyController::class, 'destroyMedia'])->name('properties.media.destroy');
        Route::post('/properties/{property}/publish', [PropertyController::class, 'publish'])->name('properties.publish');
        Route::post('/properties/{property}/unpublish', [PropertyController::class, 'unpublish'])->name('properties.unpublish');

        // Property creation wizard — Info -> Location -> Amenities -> Documents -> Units -> Review.
        // A real Draft row is created once Location (step 2) is saved; steps
        // 3-6 operate on that row. See plans/property-creation-wizard.md.
        Route::get('/properties/create', [PropertyWizardController::class, 'createInfo'])->name('properties.create');
        Route::post('/properties/wizard/info', [PropertyWizardController::class, 'storeInfo'])->name('properties.wizard.info.store');
        Route::get('/properties/wizard/location', [PropertyWizardController::class, 'createLocation'])->name('properties.wizard.location.create');
        Route::post('/properties/wizard/location', [PropertyWizardController::class, 'storeLocation'])->name('properties.wizard.location.store');

        Route::get('/properties/{property}/wizard', [PropertyWizardController::class, 'resume'])->name('properties.wizard.resume');
        Route::get('/properties/{property}/wizard/info', [PropertyWizardController::class, 'editInfo'])->name('properties.wizard.info.edit');
        Route::put('/properties/{property}/wizard/info', [PropertyWizardController::class, 'updateInfo'])->name('properties.wizard.info.update');
        Route::get('/properties/{property}/wizard/location', [PropertyWizardController::class, 'editLocation'])->name('properties.wizard.location.edit');
        Route::put('/properties/{property}/wizard/location', [PropertyWizardController::class, 'updateLocation'])->name('properties.wizard.location.update');
        Route::get('/properties/{property}/wizard/amenities', [PropertyWizardController::class, 'amenities'])->name('properties.wizard.amenities');
        Route::post('/properties/{property}/wizard/amenities', [PropertyWizardController::class, 'storeAmenities'])->name('properties.wizard.amenities.store');
        Route::get('/properties/{property}/wizard/documents', [PropertyWizardController::class, 'documents'])->name('properties.wizard.documents');
        Route::get('/properties/{property}/wizard/units', [PropertyWizardController::class, 'units'])->name('properties.wizard.units');
        Route::get('/properties/{property}/wizard/review', [PropertyWizardController::class, 'review'])->name('properties.wizard.review');
        Route::post('/properties/{property}/wizard/submit', [PropertyWizardController::class, 'submit'])->name('properties.wizard.submit');
    });

    // Tenant-accessible routes
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favorites/{propertyId}/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

    // Tenant-specific routes
    Route::middleware('tenant')->prefix('tenant')->group(function () {
        Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');

        Route::get('/reservations/{reservation}/agreement', [AgreementController::class, 'show'])->name('agreements.show');
        Route::post('/reservations/{reservation}/agreement/sign', [AgreementController::class, 'sign'])->name('agreements.sign');
        Route::post('/reservations/{reservation}/pay', [PaymentController::class, 'createCheckoutSession'])->name('payments.checkout');
        Route::get('/reservations/{reservation}/payment-success', [PaymentController::class, 'success'])->name('payments.success');
        Route::post('/reservations/{reservation}/confirm-move-in', [AgreementController::class, 'confirmMoveIn'])->name('agreements.confirmMoveIn');
        // confirmMoveIn is a state-changing POST-only action, but a reload/bookmark/
        // back-forward navigation to this URL lands here as GET — send it to the
        // agreement page instead of surfacing a raw 405.
        Route::get('/reservations/{reservation}/confirm-move-in', fn (\App\Models\Reservation $reservation) => redirect()->route('agreements.show', $reservation));
        Route::post('/reservations/{reservation}/dispute-move-in', [AgreementController::class, 'disputeMoveIn'])->name('agreements.disputeMoveIn');

        // A tenant's own tenancy and rent ledger — read-only except for the
        // one earliest unsettled period, which can be paid online via GCash.
        Route::get('/tenancy/{reservation}', [App\Http\Controllers\Tenant\TenancyController::class, 'show'])->name('tenancy.show');
        Route::post('/tenancy/{reservation}/pay-rent', [PaymentController::class, 'createRentCheckoutSession'])->name('payments.rentCheckout');
        Route::get('/tenancy/{reservation}/rent-success', [PaymentController::class, 'rentSuccess'])->name('payments.rent.success');

        Route::post('/reviews', [\App\Http\Controllers\Tenant\ReviewController::class, 'store'])->name('reviews.store');

        // Reports
        Route::get('/reports', [App\Http\Controllers\Tenant\ReportController::class, 'index'])->name('tenant.reports.index');

        // Profile
        Route::get('/profile', [TenantProfileController::class, 'show'])->name('tenant.profile.show');
        Route::get('/profile/edit', [TenantProfileController::class, 'edit'])->name('tenant.profile.edit');
        Route::patch('/profile', [TenantProfileController::class, 'update'])->name('tenant.profile.update');
    });
    // Public landlord profile (visibility-gated in controller)
    Route::get('/landlord/{user}/profile', [App\Http\Controllers\Landlord\ProfileController::class, 'show'])->name('landlord.profile.show');


    // Landlord-specific prefix routes
    Route::middleware('landlord')->prefix('landlord')->name('landlord.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        // Property management
        Route::get('/properties', [LandlordPropertyController::class, 'index'])->name('properties.index');
        Route::get('/properties/{property}', [LandlordPropertyController::class, 'show'])->name('properties.show');

      // Reservations
        Route::get('/reservations', [App\Http\Controllers\Landlord\ReservationController::class, 'index'])->name('reservations.index');
        Route::get('/reservations/export', [App\Http\Controllers\Landlord\ReservationController::class, 'export'])->name('reservations.export');
        Route::patch('/reservations/{reservation}/reject', [App\Http\Controllers\Landlord\ReservationController::class, 'reject'])->name('reservations.reject');
        Route::patch('/reservations/{reservation}/cancel', [App\Http\Controllers\Landlord\ReservationController::class, 'cancel'])->name('reservations.cancel');
        Route::patch('/reservations/{reservation}/advance-negotiation', [App\Http\Controllers\Landlord\ReservationController::class, 'advanceToNegotiation'])->name('reservations.advanceNegotiation');
        Route::patch('/reservations/{reservation}/advance-agreement', [App\Http\Controllers\Landlord\ReservationController::class, 'advanceToPendingAgreement'])->name('reservations.advanceAgreement');
        Route::post('/reservations/{reservation}/turned-over', [App\Http\Controllers\Landlord\ReservationController::class, 'markTurnedOver'])->name('reservations.markTurnedOver');

        // Units
        Route::resource('properties.units', PropertyUnitController::class);
        Route::delete('/properties/{property}/units/{unit}/media/{media}', [PropertyUnitController::class, 'destroyMedia'])->name('properties.units.media.destroy');

        // Property verification documents (proof of ownership, tax dec, permits — private disk, policy-gated)
        Route::get('/properties/{property}/documents', [LandlordPropertyDocumentController::class, 'index'])->name('properties.documents.index');
        Route::post('/properties/{property}/documents', [LandlordPropertyDocumentController::class, 'store'])->name('properties.documents.store');
        Route::post('/properties/{property}/documents/{document}/replace', [LandlordPropertyDocumentController::class, 'replace'])->name('properties.documents.replace');
        Route::delete('/properties/{property}/documents/{document}', [LandlordPropertyDocumentController::class, 'destroy'])->name('properties.documents.destroy');
        Route::get('/properties/{property}/documents/{document}/preview', [LandlordPropertyDocumentController::class, 'preview'])->name('properties.documents.preview');
        Route::get('/properties/{property}/documents/{document}/download', [LandlordPropertyDocumentController::class, 'download'])->name('properties.documents.download');

        // Occupancy monitoring
        Route::get('/occupancy', [App\Http\Controllers\Landlord\OccupancyController::class, 'index'])->name('occupancy.index');
        Route::get('/occupancy/export', [App\Http\Controllers\Landlord\OccupancyController::class, 'export'])->name('occupancy.export');


        Route::patch('/reviews/{review}/reply', [\App\Http\Controllers\Landlord\ReviewController::class, 'reply'])->name('reviews.reply');

        // Profile
        Route::get('/profile', [App\Http\Controllers\Landlord\ProfileController::class, 'me'])->name('profile.me');
        Route::get('/profile/edit', [App\Http\Controllers\Landlord\ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [App\Http\Controllers\Landlord\ProfileController::class, 'update'])->name('profile.update');

        // Tenant Ratings
        Route::get('/reservations/{reservation}/rate-tenant', [App\Http\Controllers\Landlord\TenantRatingController::class, 'create'])->name('reservations.rateTenant');
        Route::post('/reservations/{reservation}/rate-tenant', [App\Http\Controllers\Landlord\TenantRatingController::class, 'store'])->name('reservations.rateTenant.store');

        // Tenants
        Route::get('/tenants', [App\Http\Controllers\Landlord\TenantController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/export', [App\Http\Controllers\Landlord\TenantController::class, 'export'])->name('tenants.export');

        // Walk-in tenants — tenancies agreed offline and recorded after the
        // fact. Registered before any /tenants/{param} route so the literal
        // segments can't be swallowed by a wildcard.
        Route::get('/tenants/walk-in/create', [App\Http\Controllers\Landlord\WalkInTenantController::class, 'create'])->name('tenants.walkIn.create');
        Route::post('/tenants/walk-in', [App\Http\Controllers\Landlord\WalkInTenantController::class, 'store'])->name('tenants.walkIn.store');

        // A single tenancy and its rent ledger. Serves walk-in and platform
        // tenancies alike — the escrow only ever covered the initial payment.
        Route::get('/tenancies/{reservation}', [App\Http\Controllers\Landlord\TenancyController::class, 'show'])->name('tenancies.show');
        Route::post('/tenancies/{reservation}/end', [App\Http\Controllers\Landlord\TenancyController::class, 'endTenancy'])->name('tenancies.end');
        // On-demand rent reminder; throttled so a jumpy landlord can't spam a tenant.
        Route::post('/tenancies/{reservation}/remind', [App\Http\Controllers\Landlord\TenancyController::class, 'remind'])
            ->middleware('throttle:10,1')->name('tenancies.remind');

        // Rent collection
        Route::get('/payments', [App\Http\Controllers\Landlord\PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/export', [App\Http\Controllers\Landlord\PaymentController::class, 'export'])->name('payments.export');
        Route::post('/tenancies/{reservation}/payments', [App\Http\Controllers\Landlord\PaymentController::class, 'store'])->name('payments.store');
        Route::get('/payments/{payment}/receipt', [App\Http\Controllers\Landlord\PaymentController::class, 'receipt'])->name('payments.receipt');

        // A landlord's own view of what AbangananHub owes them and has paid
        // out. See docs/specs/2026-07-26-landlord-payout-design.md.
        Route::get('/payouts', [App\Http\Controllers\Landlord\PayoutController::class, 'index'])->name('payouts.index');

        // Reviews
        Route::get('/reviews', [App\Http\Controllers\Landlord\ReviewController::class, 'index'])->name('reviews.index');

        // Analytics — rental business performance (INSIGHTS)
        Route::get('/analytics', [App\Http\Controllers\Landlord\AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/analytics/export', [App\Http\Controllers\Landlord\AnalyticsController::class, 'export'])->name('analytics.export');

        // My Complaints — reports this landlord has filed (ACCOUNT).
        // Renamed from landlord.reports.* so "Reports" only ever means analytics.
        Route::get('/complaints', [App\Http\Controllers\Landlord\ReportController::class, 'index'])->name('complaints.index');
        // Units (standalone index)
        Route::get('/units', [App\Http\Controllers\Landlord\UnitIndexController::class, 'index'])->name('units.index');
        Route::get('/units/export', [App\Http\Controllers\Landlord\UnitIndexController::class, 'export'])->name('units.export');
    });

    // Admin-specific routes
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/listings/approval', [ListingController::class, 'approval'])->name('listings.approval');
        Route::post('/listings/{property_id}/approve', [ListingController::class, 'approve'])->name('listings.approve');
        Route::post('/listings/{property_id}/reject', [ListingController::class, 'reject'])->name('listings.reject');
        Route::post('/listings/{property_id}/unsuspend', [ListingController::class, 'unsuspend'])->name('listings.unsuspend');

        Route::get('/verifications', [AdminVerificationController::class, 'index'])->name('verifications.index');
        Route::get('/verifications/{verification}', [AdminVerificationController::class, 'show'])->name('verifications.show');
        Route::post('/verifications/{verification}/approve', [AdminVerificationController::class, 'approve'])->name('verifications.approve');
        Route::post('/verifications/{verification}/reject', [AdminVerificationController::class, 'reject'])->name('verifications.reject');

        // Property verification documents — cross-property queue, mirrors Landlord Verifications.
        Route::get('/documents', [AdminPropertyDocumentController::class, 'index'])->name('documents.index');
        Route::get('/documents/{document}', [AdminPropertyDocumentController::class, 'show'])->name('documents.show');
        Route::get('/documents/{document}/preview', [AdminPropertyDocumentController::class, 'preview'])->name('documents.preview');
        Route::get('/documents/{document}/download', [AdminPropertyDocumentController::class, 'download'])->name('documents.download');

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
        Route::patch('/reviews/{review}/toggle-hidden', [AdminReviewController::class, 'toggleHidden'])->name('reviews.toggleHidden');

        Route::get('/conversations', [AdminConversationController::class, 'index'])->name('conversations.index');
        Route::get('/conversations/{conversation}', [AdminConversationController::class, 'show'])->name('conversations.show');

        Route::get('/units', [AdminPropertyUnitController::class, 'index'])->name('units.index');
        Route::get('/properties/{property}/units/{unit}', [AdminPropertyUnitController::class, 'show'])->name('units.show');
        Route::post('/properties/{property}/units/{unit}/approve', [AdminPropertyUnitController::class, 'approve'])->name('units.approve');
        Route::post('/properties/{property}/units/{unit}/reject', [AdminPropertyUnitController::class, 'reject'])->name('units.reject');

        // Property verification documents — review lands on the catalogue "show" page.
        Route::post('/properties/{property}/documents/request', [AdminPropertyDocumentController::class, 'request'])->name('properties.documents.request');
        Route::post('/properties/{property}/documents/{document}/verify', [AdminPropertyDocumentController::class, 'verify'])->name('properties.documents.verify');
        Route::post('/properties/{property}/documents/{document}/reject', [AdminPropertyDocumentController::class, 'reject'])->name('properties.documents.reject');

        // Property & unit catalogues — full inventory across all statuses,
        // distinct from the approval queues above.
        Route::get('/catalogue/properties/export', [PropertyCatalogueController::class, 'export'])->name('catalogue.properties.export');
        Route::get('/catalogue/properties/{property}', [PropertyCatalogueController::class, 'show'])->name('catalogue.properties.show');
        Route::get('/catalogue/properties', [PropertyCatalogueController::class, 'index'])->name('catalogue.properties.index');
        Route::get('/catalogue/units/export', [UnitCatalogueController::class, 'export'])->name('catalogue.units.export');
        Route::get('/catalogue/units', [UnitCatalogueController::class, 'index'])->name('catalogue.units.index');

        Route::get('/reports', [App\Http\Controllers\Admin\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{report}', [App\Http\Controllers\Admin\ReportController::class, 'show'])->name('reports.show');
        Route::patch('/reports/{report}/resolve', [App\Http\Controllers\Admin\ReportController::class, 'resolve'])->name('reports.resolve');

        Route::get('/payments', [App\Http\Controllers\Admin\PaymentController::class, 'index'])->name('payments.index');
        Route::post('/payments/{payment}/release', [App\Http\Controllers\Admin\PaymentController::class, 'release'])->name('payments.release');

        // Landlord payouts — manual GCash transfer queue, see
        // docs/specs/2026-07-26-landlord-payout-design.md
        Route::get('/payouts', [App\Http\Controllers\Admin\PayoutController::class, 'index'])->name('payouts.index');
        Route::post('/payouts/{user}/mark-paid-out', [App\Http\Controllers\Admin\PayoutController::class, 'markPaidOut'])->name('payouts.markPaidOut');

        // Profile
        Route::get('/profile', [App\Http\Controllers\Admin\ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [App\Http\Controllers\Admin\ProfileController::class, 'update'])->name('profile.update');

       Route::get('/report-analytics', [ReportAnalyticsController::class, 'index'])->name('report-analytics.index');
       Route::get('/report-analytics/export', [ReportAnalyticsController::class, 'export'])->name('report-analytics.export');

        // Overall ratings — platform-wide averages across the rating relationships
        Route::get('/ratings', [App\Http\Controllers\Admin\RatingController::class, 'index'])->name('ratings.index');

        // Audit logs — read-only by design; audit rows are append-only.
        Route::get('/audit-logs', [App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit-logs.index');

        // System settings — whitelisted overrides of config/rentals.php
        Route::get('/settings', [App\Http\Controllers\Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::patch('/settings', [App\Http\Controllers\Admin\SettingController::class, 'update'])->name('settings.update');
    });

   // Conversations and messages
    Route::get('/conversations/recent-messages', [ConversationController::class, 'recentMessages'])->name('conversations.recentMessages');
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
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

// Bridges a mobile WebView (Bearer token, no session) into a logged-in web
// session — see Api\AuthController::webviewTicket. Outside the auth
// middleware on purpose: the WebView has no session to require yet.
Route::get('/auth/webview-login/{ticket}', WebviewLoginController::class)->name('auth.webviewLogin');
require __DIR__.'/auth.php';