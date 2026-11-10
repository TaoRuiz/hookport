<?php

namespace App\Delivery;

use App\Enum\DeliveryOutcome;

/**
 * Protocol-neutral result of one delivery attempt, so retries and history do not depend on the transport.
 */
final readonly class DeliveryResult
{
    /**
     * @param ?int $responseCode null when the target gave no response (timeout, connection error)
     */
    public function __construct(
        public DeliveryOutcome $outcome,
        public ?int $responseCode = null,
        public ?string $responseExcerpt = null,
        public ?string $failureReason = null,
    ) {
    }
}
