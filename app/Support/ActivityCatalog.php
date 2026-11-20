<?php

namespace App\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Str;

/**
 * What the activity log records and how it words it. The log is automatic (LogUserActivity logs every
 * state-changing request), so this file only decides three things:
 *   1. which requests are NOT actions (noise),
 *   2. which GET requests ARE actions (downloads, exports, opening a private document),
 *   3. the sentence shown for a route. A route with no entry still gets a readable sentence from fallback().
 *
 * To log a new route, do nothing. To word it better, add one line to LABELS.
 */
class ActivityCatalog
{
    /** Route names that change state but are not something a person did: pings, previews, third-party callbacks. */
    public const IGNORED_ROUTES = [
        'csp.report', 'conversations.read', 'notifications.read', 'notifications.readAll',
        'search.interpret', 'search.results',
        'landlord.lease-templates.preview', 'landlord.reservations.leasePreview', 'landlord.verification.ocrCheck',
        'storage.local.upload', 'webhooks.paymongo',
    ];

    /** Route name prefixes that are never logged (developer tooling). */
    public const IGNORED_PREFIXES = ['debugbar.', 'ignition.', 'livewire.'];

    /** Unnamed routes ("METHOD uri", api/v1 removed) that are not actions. */
    public const IGNORED_PATHS = [
        'POST broadcasting/auth', 'GET broadcasting/auth', 'PATCH profile/push-token',
        'POST notifications/read-all', 'POST notifications/{notification}/read',
    ];

    /** GET routes that hand over a file or a private record: downloading is an action, browsing a page is not. */
    public const GET_ACTIONS = [
        'admin.audit-logs.export', 'admin.security-events.export', 'admin.user-activity.export',
        'admin.catalogue.properties.export', 'admin.catalogue.units.export', 'admin.report-analytics.export',
        'admin.documents.download', 'admin.documents.preview',
        'landlord.analytics.export', 'landlord.payments.export', 'landlord.reservations.export',
        'landlord.tenants.export', 'landlord.units.export',
        'landlord.leases.file', 'landlord.payments.receipt',
        'landlord.properties.documents.download', 'landlord.properties.documents.preview',
        'landlord.tenancies.tenantDetails.id',
        'tenancy.lease.file', 'tenancy.receipt', 'tenancy-documents.file',
        'verifications.document', 'verifications.selfie',
        // Sign-in and verification links that arrive as a GET.
        'social.callback', 'auth.webviewLogin', 'verification.verify',
    ];

    public const LABELS = [
        // Account
        'logout'                        => 'Signed out',
        'social.callback'               => 'Signed in with a social account',
        'auth.webviewLogin'             => 'Signed in through the mobile app',
        'verification.verify'           => 'Verified their email',
        'password.email'                => 'Asked for a password reset link',
        'password.store'                => 'Reset their password',
        'password.update'               => 'Changed their password',
        'two-factor.verify'             => 'Entered a two-factor code',
        'verification.send'             => 'Asked for a new verification email',
        'profile.update'                => 'Updated their profile',
        'profile.photo'                 => 'Changed their profile photo',
        'profile.destroy'               => 'Deleted their own account',
        'profile.tenantDetails.update'  => 'Updated their tenant details',
        'account.sessions.destroy'      => 'Signed a device out',
        'account.sessions.destroyOthers' => 'Signed out all other devices',
        'account.on-hold.dispute'       => 'Disputed a payment hold',
        'platform-rating.store'         => 'Rated the platform',
        'invite.store'                  => 'Sent an invite',

        // Tenant
        'favorites.toggle'              => 'Saved or removed a favourite',
        'reservations.store'            => 'Sent a reservation request',
        'reservations.cancel'           => 'Cancelled a reservation',
        'reservations.requestRefund'    => 'Asked for a refund',
        'reservations.escalateRefund'   => 'Escalated a refund request',
        'agreements.sign'               => 'Signed the rental agreement',
        'agreements.confirmMoveIn'      => 'Confirmed move-in',
        'agreements.confirmDirectMoveIn' => 'Confirmed a direct move-in',
        'agreements.disputeMoveIn'      => 'Disputed move-in',
        'payments.checkout'             => 'Started a payment',
        'payments.continue'             => 'Continued a payment',
        'payments.cancel'               => 'Cancelled a payment',
        'payments.rentCheckout'         => 'Started a rent payment',
        'paymentClaims.store'           => 'Reported a direct payment',
        'paymentClaims.dispute'         => 'Disputed a payment claim',
        'paymentClaims.withdraw'        => 'Withdrew a payment claim',
        'paymentQr.request'             => 'Asked for a payment QR',
        'paymentQr.store'               => 'Sent a payment QR',
        'reviews.store'                 => 'Posted a review',
        'reports.store'                 => 'Filed a report',
        'tenancy-documents.upload'      => 'Uploaded a tenancy document',
        'tenancy-documents.approve'     => 'Approved a tenancy document',
        'tenancy-documents.reject'      => 'Rejected a tenancy document',
        'tenancy-documents.reopen'      => 'Reopened a tenancy document',
        'tenancy-documents.waive'       => 'Waived a tenancy document',
        'handover.propose'              => 'Proposed a handover',
        'handover.confirm'              => 'Confirmed a handover',
        'viewings.store'                => 'Asked for a viewing',
        'viewings.confirm'              => 'Confirmed a viewing',
        'viewings.decline'              => 'Declined a viewing',
        'viewings.cancel'               => 'Cancelled a viewing',
        'viewings.reschedule'           => 'Rescheduled a viewing',

        // Chat
        'conversations.store'           => 'Started a conversation',
        'conversations.adminReview.request' => 'Asked an administrator to review a conversation',
        'conversations.resolve'         => 'Marked a conversation resolved',
        'messages.store'                => 'Sent a message',
        'concerns.store'                => 'Raised a concern',
        'concerns.status'               => 'Changed a concern status',

        // Landlord: listings
        'properties.wizard.info.store'      => 'Started a listing',
        'properties.wizard.info.update'     => 'Edited listing details',
        'properties.wizard.location.store'  => 'Set a listing location',
        'properties.wizard.location.update' => 'Edited a listing location',
        'properties.wizard.amenities.store' => 'Set listing amenities',
        'properties.wizard.submit'          => 'Submitted a listing for review',
        'properties.update'                 => 'Updated a listing',
        'properties.publish'                => 'Published a listing',
        'properties.unpublish'              => 'Unpublished a listing',
        'properties.destroy'                => 'Deleted a listing',
        'properties.media.destroy'          => 'Removed a listing photo',
        'landlord.properties.units.store'   => 'Added a unit',
        'landlord.properties.units.update'  => 'Updated a unit',
        'landlord.properties.units.destroy' => 'Deleted a unit',
        'landlord.properties.units.media.destroy' => 'Removed a unit photo',
        'landlord.properties.documents.store'   => 'Uploaded a property document',
        'landlord.properties.documents.replace' => 'Replaced a property document',
        'landlord.properties.documents.destroy' => 'Deleted a property document',
        'landlord.property-scope.update'    => 'Switched the property filter',

        // Landlord: people, leases, money
        'landlord.verification.store'        => 'Submitted a landlord application',
        'landlord.verification.sendEmailLink' => 'Sent a verification email link',
        'landlord.profile.update'            => 'Updated the landlord profile',
        'landlord.paymentMethods.update'     => 'Updated payment methods',
        'landlord.tenants.store'             => 'Added a tenant',
        'landlord.tenants.update'            => 'Updated a tenant',
        'landlord.tenants.destroy'           => 'Removed a tenant',
        'landlord.tenants.invite'            => 'Invited a tenant',
        'landlord.tenancies.tenantDetails.update' => 'Updated a tenant\'s details',
        'landlord.contracts.store'           => 'Created a lease',
        'landlord.contracts.renew'           => 'Renewed a lease',
        'landlord.leases.upload'             => 'Uploaded a signed lease',
        'landlord.lease-templates.store'     => 'Created a lease template',
        'landlord.lease-templates.update'    => 'Updated a lease template',
        'landlord.lease-templates.destroy'   => 'Deleted a lease template',
        'landlord.lease-templates.assign'    => 'Assigned a lease template',
        'landlord.reservations.acceptAndSend'       => 'Accepted a request and sent the agreement',
        'landlord.reservations.advanceNegotiation'  => 'Moved a request to negotiation',
        'landlord.reservations.advanceAgreement'    => 'Sent the agreement',
        'landlord.reservations.withdrawAgreement'   => 'Withdrew the agreement',
        'landlord.reservations.reject'              => 'Rejected a reservation request',
        'landlord.reservations.cancel'              => 'Cancelled a reservation',
        'landlord.reservations.markTurnedOver'      => 'Marked the unit handed over',
        'landlord.reservations.confirmDirectMoveIn' => 'Confirmed a direct move-in',
        'landlord.reservations.confirmWalkInMoveIn' => 'Confirmed a walk-in move-in',
        'landlord.reservations.recordMoveInPayment' => 'Recorded a move-in payment',
        'landlord.reservations.settlePayment'       => 'Settled a payment',
        'landlord.reservations.rateTenant.store'    => 'Rated a tenant',
        'landlord.tenancies.end'                    => 'Ended a tenancy',
        'landlord.tenancies.remind'                 => 'Sent a rent reminder',
        'landlord.tenancies.depositReturn'          => 'Returned a deposit',
        'landlord.tenancies.stopCollecting'         => 'Stopped collecting rent',
        'landlord.tenancies.resumeCollecting'       => 'Resumed collecting rent',
        'landlord.tenancies.waiveLateFee'           => 'Waived a late fee',
        'landlord.payments.store'                   => 'Recorded a payment',
        'landlord.payments.void'                    => 'Voided a payment',
        'landlord.payments.remindOverdue'           => 'Sent overdue rent reminders',
        'landlord.paymentClaims.confirm'            => 'Confirmed a payment claim',
        'landlord.paymentClaims.reject'             => 'Rejected a payment claim',
        'landlord.depositCharges.store'             => 'Recorded a deposit charge',
        'landlord.depositCharges.void'              => 'Voided a deposit charge',
        'landlord.holds.store'                      => 'Reported a tenant for unpaid rent',
        'landlord.holds.release'                    => 'Released a tenant hold',
        'landlord.reviews.reply'                    => 'Replied to a review',
        'landlord.viewings.store'                   => 'Added a viewing slot',
        'landlord.viewings.update'                  => 'Updated a viewing',
        'landlord.viewings.destroy'                 => 'Deleted a viewing',
        'landlord.viewings.hours'                   => 'Set viewing hours',
        'landlord.viewings.block'                   => 'Blocked a viewing day',
        'landlord.viewings.unblock'                 => 'Unblocked a viewing day',

        // Admin
        'admin.profile.update'                => 'Updated the admin profile',
        'admin.stepUp.confirm'                => 'Re-entered their password for a sensitive action',
        'admin.security.two-factor.store'     => 'Turned on two-factor',
        'admin.security.two-factor.destroy'   => 'Turned off two-factor',
        'admin.security.two-factor.codes'     => 'Regenerated recovery codes',
        'admin.security.two-factor.trusted.revoke' => 'Revoked a trusted device',
        'admin.users.store'                   => 'Created a user',
        'admin.users.update'                  => 'Updated a user',
        'admin.users.updateStatus'            => 'Changed a user\'s status',
        'admin.users.destroy'                 => 'Deleted a user',
        'admin.users.passwordReset'           => 'Sent a password reset link',
        'admin.users.signOutEverywhere'       => 'Signed a user out everywhere',
        'admin.verifications.approve'         => 'Approved a landlord application',
        'admin.verifications.reject'          => 'Rejected a landlord application',
        'admin.listings.approve'              => 'Approved a listing',
        'admin.listings.reject'               => 'Rejected a listing',
        'admin.listings.suspend'              => 'Suspended a listing',
        'admin.listings.unsuspend'            => 'Unsuspended a listing',
        'admin.units.approve'                 => 'Approved a unit',
        'admin.units.reject'                  => 'Rejected a unit',
        'admin.properties.documents.request'  => 'Asked for a property document',
        'admin.properties.documents.verify'   => 'Verified a property document',
        'admin.properties.documents.reject'   => 'Rejected a property document',
        'admin.reservations.forceCancel'      => 'Force-cancelled a reservation',
        'admin.reservations.forceReject'      => 'Force-rejected a reservation',
        'admin.payments.release'              => 'Released a payment',
        'admin.payments.refund'               => 'Refunded a payment',
        'admin.deposit-charges.void'          => 'Voided a deposit charge',
        'admin.payouts.approveAccount'        => 'Approved a payout account',
        'admin.payouts.markPaidOut'           => 'Recorded a payout by hand',
        'admin.payouts.retry'                 => 'Retried a payout',
        'admin.payouts.check'                 => 'Checked a payout',
        'admin.payouts.pause'                 => 'Paused payouts',
        'admin.payouts.resume'                => 'Resumed payouts',
        'admin.tenant-holds.confirm'          => 'Confirmed a tenant hold',
        'admin.tenant-holds.reject'           => 'Rejected a tenant hold',
        'admin.tenant-holds.release'          => 'Released a tenant hold',
        'admin.hold-alerts.dismiss'           => 'Dismissed a duplicate-account alert',
        'admin.hold-alerts.hold'              => 'Put a duplicate account on hold',
        'admin.reports.resolve'               => 'Resolved a report',
        'admin.reviews.toggleHidden'          => 'Hid or showed a review',
        'admin.settings.update'               => 'Updated system settings',
        'admin.conversations.endReview'       => 'Finished reviewing a conversation',

        // Downloads and exports
        'admin.audit-logs.export'             => 'Exported the admin actions log',
        'admin.security-events.export'        => 'Exported the account and security log',
        'admin.user-activity.export'          => 'Exported the activity log',
        'admin.catalogue.properties.export'   => 'Exported the property list',
        'admin.catalogue.units.export'        => 'Exported the unit list',
        'admin.report-analytics.export'       => 'Exported report analytics',
        'admin.documents.download'            => 'Downloaded a property document',
        'admin.documents.preview'             => 'Opened a property document',
        'landlord.analytics.export'           => 'Exported analytics',
        'landlord.payments.export'            => 'Exported payments',
        'landlord.reservations.export'        => 'Exported reservations',
        'landlord.tenants.export'             => 'Exported tenants',
        'landlord.units.export'               => 'Exported units',
        'landlord.leases.file'                => 'Opened a signed lease',
        'landlord.payments.receipt'           => 'Opened a payment receipt',
        'landlord.properties.documents.download' => 'Downloaded a property document',
        'landlord.properties.documents.preview'  => 'Opened a property document',
        'landlord.tenancies.tenantDetails.id' => 'Viewed a tenant\'s ID number',
        'tenancy.lease.file'                  => 'Opened their lease',
        'tenancy.receipt'                     => 'Opened a payment receipt',
        'tenancy-documents.file'              => 'Opened a tenancy document',
        'verifications.document'              => 'Opened a landlord\'s ID photo',
        'verifications.selfie'                => 'Opened a landlord\'s selfie',
    ];

    /** Routes with no name (the mobile API, and a few web forms). Keyed "METHOD uri" with the api/v1/ prefix removed. */
    public const PATH_LABELS = [
        'POST login'                                 => 'Signed in',
        'POST register'                              => 'Created an account',
        'POST confirm-password'                      => 'Confirmed their password',
        'POST auth/login'                            => 'Signed in',
        'POST auth/logout'                           => 'Signed out',
        'POST auth/{provider}/token'                 => 'Signed in with a social account',
        'POST auth/register'                         => 'Created an account',
        'POST auth/webview-ticket'                   => 'Opened the web app from the mobile app',
        'POST conversations'                         => 'Started a conversation',
        'POST conversations/{conversation}/messages' => 'Sent a message',
        'POST conversations/{conversation}/resolve'  => 'Marked a conversation resolved',
        'POST favorites/{propertyId}/toggle'         => 'Saved or removed a favourite',
        'PATCH profile'                              => 'Updated their profile',
        'PATCH profile/password'                     => 'Changed their password',
        'PATCH landlord/profile/me'                  => 'Updated the landlord profile',
        'POST reports'                               => 'Filed a report',
        'POST reviews'                               => 'Posted a review',
        'POST reservations/{reservation}/handover/propose' => 'Proposed a handover',
        'POST reservations/{reservation}/handover/confirm' => 'Confirmed a handover',
        'POST tenant/reservations'                   => 'Sent a reservation request',
        'PATCH tenant/reservations/{reservation}/cancel' => 'Cancelled a reservation',
        'POST tenant/reservations/{reservation}/agreement/sign' => 'Signed the rental agreement',
        'POST tenant/reservations/{reservation}/confirm-move-in' => 'Confirmed move-in',
        'POST tenant/reservations/{reservation}/dispute-move-in' => 'Disputed move-in',
        'POST tenant/reservations/{reservation}/pay' => 'Started a payment',
        'POST tenant/reservations/{reservation}/payment/reconcile' => 'Checked a payment',
        'POST tenant/tenancy/{reservation}/pay-rent' => 'Started a rent payment',
        'POST tenant/tenancy/{reservation}/payment/reconcile' => 'Checked a rent payment',
        'POST landlord/properties'                   => 'Created a listing',
        'PUT landlord/properties/{property}'         => 'Updated a listing',
        'PATCH landlord/properties/{property}'       => 'Updated a listing',
        'DELETE landlord/properties/{property}'      => 'Deleted a listing',
        'DELETE landlord/properties/{property}/media/{media}' => 'Removed a listing photo',
        'POST landlord/properties/{property}/units'  => 'Added a unit',
        'PUT landlord/properties/{property}/units/{unit}'   => 'Updated a unit',
        'PATCH landlord/properties/{property}/units/{unit}' => 'Updated a unit',
        'DELETE landlord/properties/{property}/units/{unit}' => 'Deleted a unit',
        'DELETE landlord/properties/{property}/units/{unit}/media/{media}' => 'Removed a unit photo',
        'PATCH landlord/reservations/{reservation}/advance-negotiation' => 'Moved a request to negotiation',
        'PATCH landlord/reservations/{reservation}/advance-agreement'   => 'Sent the agreement',
        'PATCH landlord/reservations/{reservation}/cancel'  => 'Cancelled a reservation',
        'PATCH landlord/reservations/{reservation}/reject'  => 'Rejected a reservation request',
        'POST landlord/reservations/{reservation}/rate-tenant' => 'Rated a tenant',
        'POST landlord/reservations/{reservation}/turned-over' => 'Marked the unit handed over',
        'PATCH landlord/reviews/{review}/reply'      => 'Replied to a review',
        'POST landlord/tenancies/{reservation}/end'  => 'Ended a tenancy',
        'POST landlord/tenancies/{reservation}/payments' => 'Recorded a payment',
        'POST landlord/tenancies/{reservation}/remind'   => 'Sent a rent reminder',
        'POST landlord/tenants/walk-in'              => 'Added a walk-in tenant',
    ];

    /** Should this request be written to the log at all? */
    public static function shouldLog(Route $route, string $method): bool
    {
        $name = $route->getName();

        if ($name) {
            foreach (self::IGNORED_PREFIXES as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    return false;
                }
            }
            if (in_array($name, self::IGNORED_ROUTES, true)) {
                return false;
            }
        } elseif (in_array($method . ' ' . self::pathKey($route), self::IGNORED_PATHS, true)) {
            return false;
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        return $method === 'GET' && $name !== null && in_array($name, self::GET_ACTIONS, true);
    }

    /** The key stored in `action`: the route name, or "METHOD uri" when the route has none. */
    public static function actionKey(Route $route, string $method): string
    {
        return $route->getName() ?: $method . ' ' . self::pathKey($route);
    }

    /** The sentence shown for a request. */
    public static function summary(Route $route, string $method): string
    {
        $name = $route->getName();

        if ($name && isset(self::LABELS[$name])) {
            return self::LABELS[$name];
        }

        return self::PATH_LABELS[$method . ' ' . self::pathKey($route)] ?? self::fallback($route, $method);
    }

    /** The route pattern without the api/v1/ prefix: "landlord/properties/{property}". */
    public static function pathKey(Route $route): string
    {
        return ltrim(preg_replace('~^/api/v1~', '', '/' . ltrim($route->uri(), '/')), '/');
    }

    /** A readable sentence for a route nobody has worded: "landlord.foo-bar.store" becomes "Created foo bar". */
    private static function fallback(Route $route, string $method): string
    {
        $name = $route->getName();
        $parts = $name
            ? explode('.', $name)
            : array_values(array_filter(explode('/', $route->uri()), fn ($s) => $s !== '' && $s !== 'api' && $s !== 'v1' && ! str_starts_with($s, '{')));

        $parts = array_values(array_filter($parts, fn ($p) => ! in_array($p, ['admin', 'landlord', 'tenant'], true)));
        $verbPart = array_pop($parts) ?? '';

        $verb = match ($verbPart) {
            'store'   => 'Created',
            'update'  => 'Updated',
            'destroy' => 'Deleted',
            default   => match ($method) {
                'PUT', 'PATCH' => 'Updated',
                'DELETE'       => 'Deleted',
                default        => 'Did',
            },
        };

        $subject = $parts;
        if (! in_array($verbPart, ['store', 'update', 'destroy'], true)) {
            $subject[] = $verbPart;
        }

        $words = trim(str_replace(['-', '_'], ' ', implode(' ', array_map(fn ($s) => Str::snake($s, ' '), $subject))));

        return trim($verb . ' ' . ($words !== '' ? $words : 'something'));
    }
}
