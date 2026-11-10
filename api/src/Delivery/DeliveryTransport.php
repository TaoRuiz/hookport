<?php

namespace App\Delivery;

use App\Entity\EventIn;
use App\Entity\Target;

/**
 * Sends one event to one target over the target's protocol. One implementation per protocol.
 */
interface DeliveryTransport
{
    /**
     * Never throws on a delivery failure: the failure is described by the returned result.
     */
    public function deliver(EventIn $eventIn, Target $target, int $attemptNumber): DeliveryResult;
}
