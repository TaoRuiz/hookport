<?php

namespace App\Enum;

/**
 * Global state of an event, derived from its deliveries and never stored.
 */
enum EventState: string
{
    case Unrouted = 'unrouted';
    case InProgress = 'in_progress';
    case Delivered = 'delivered';
    case NeedsAttention = 'needs_attention';

    /**
     * A failed delivery wins over ongoing ones: it needs a human whatever happens to the others.
     */
    public static function fromDeliveryCounts(int $deliveriesCount, int $failedDeliveriesCount, int $ongoingDeliveriesCount): self
    {
        if (0 === $deliveriesCount) {
            return self::Unrouted;
        }
        if ($failedDeliveriesCount > 0) {
            return self::NeedsAttention;
        }
        if ($ongoingDeliveriesCount > 0) {
            return self::InProgress;
        }

        return self::Delivered;
    }
}
