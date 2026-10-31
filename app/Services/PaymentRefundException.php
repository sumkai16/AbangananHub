<?php

namespace App\Services;

use RuntimeException;

/**
 * A refund that was refused. The message is written for the admin and is shown as is. $httpStatus 409 means the
 * payment is not in a state that can be refunded (someone else got there first); anything else means PayMongo
 * or the input said no, and nothing was changed.
 */
class PaymentRefundException extends RuntimeException
{
    public function __construct(string $message, public readonly int $httpStatus = 422)
    {
        parent::__construct($message);
    }
}
