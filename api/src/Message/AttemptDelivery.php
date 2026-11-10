<?php

namespace App\Message;

use Symfony\Component\Uid\Uuid;

/**
 * Asks the worker to make the next attempt of one delivery. Re-dispatched with a delay on retryable failures.
 */
final readonly class AttemptDelivery
{
    public function __construct(
        public Uuid $deliveryUuid,
    ) {
    }
}
