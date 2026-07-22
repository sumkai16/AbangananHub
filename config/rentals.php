<?php

return [
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
];
