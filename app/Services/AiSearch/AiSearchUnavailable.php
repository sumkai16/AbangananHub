<?php

namespace App\Services\AiSearch;

use RuntimeException;

/** The AI could not answer (no key, timeout, quota, bad JSON). The service falls back to keyword search. */
class AiSearchUnavailable extends RuntimeException
{
}
