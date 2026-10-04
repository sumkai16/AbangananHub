<?php

return [
    /*
     * Version tag for the Terms & Conditions / Privacy Policy. The property page remembers a visitor's
     * "I agree" tick on their device against this value — change it whenever the legal text changes and
     * everyone is asked once more.
     */
    'terms_version' => '2026-09',
    /*
     * Clock 2 — days the tenant has to confirm move-in after keys are turned over.
     * Expiry releases the held deposit to the landlord.
     */
    'move_in_confirmation_days' => 7,

    /*
     * Clock 1 — days past the agreed move-in date before an un-turned-over
     * reservation is escalated to admin review.
     */
    'turnover_grace_days' => 7,

    /*
     * Clock 1 fallback when the reservation has no target_move_in_date
     * (the column is nullable). Counted from the payment date instead.
     */
    'turnover_grace_days_no_date' => 14,

    /*
     * Days-remaining thresholds that trigger a Clock 2 reminder.
     * [4, 1, 0] on a 7-day window = day 3, day 6, and the morning of expiry.
     */
    'reminder_days_remaining' => [4, 1, 0],

    /*
     * Ceiling on how far a confirmed handover slot may push Clock 1's deadline,
     * measured from the ORIGINAL deadline rather than from now — otherwise each
     * reschedule buys another full window and the escrow never escalates.
     * Repeated reschedules converge on this bound instead of walking forever.
     */
    'handover_max_extension_days' => 30,

    /*
     * Rent ledger — day of the month rent falls due, used only when the
     * reservation carries neither its own rent_due_day nor a move-in date to
     * take the day from. Capped at 28 by Reservation::rentDueDay() so the day
     * exists in February.
     */
    'rent_due_day_default' => 1,

    /*
     * Days past the due date before a billing period reads as Overdue rather
     * than Due. Zero means the day after is already late, which matches how an
     * informal rental is actually collected; raise it if landlords want a
     * documented grace window.
     */
    'rent_overdue_grace_days' => 0,

    /*
     * Rent reminders — how far before the due date the "due soon" nudge fires.
     */
    'rent_reminder_lead_days' => 3,

    /*
     * How often the overdue reminder repeats while a period stays unpaid.
     */
    'rent_overdue_reminder_interval_days' => 7,

    /*
     * Stop reminding after this many overdue weeks, so a tenancy the landlord
     * forgot to end doesn't generate reminders forever.
     */
    'rent_reminder_max_overdue_weeks' => 8,

    /*
     * Unit viewings — hourly slots from the first to the last start hour
     * (24h), and how far ahead a viewing can be booked.
     */
    'viewing_first_hour' => 8,
    'viewing_last_hour' => 17,
    'viewing_max_days_ahead' => 60,

    /*
     * Bounds for a landlord's own weekly viewing hours (24h, whole hours).
     * The first/last hour above is only the default for landlords who
     * haven't set their hours yet — and the range offline viewings use.
     */
    'viewing_hours_earliest' => 6,
    'viewing_hours_latest' => 21,

    /*
     * Days of written notice either party gives to end a tenancy, as printed
     * in the lease (App\Support\LeaseTerms). Frozen into each lease when it's
     * issued, so changing this only affects leases issued afterwards.
     */
    'lease_notice_days' => 30,

    /*
     * Rental statuses in which the tenant may request a viewing: after the
     * landlord accepts the inquiry, up to signing.
     */
    'viewing_statuses' => ['Under Negotiation', 'Pending Rental Agreement'],
];
