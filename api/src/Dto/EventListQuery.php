<?php

namespace App\Dto;

use App\Enum\EventState;

/**
 * Every filter is optional; filters left out keep every event.
 */
final readonly class EventListQuery
{
    /**
     * @param list<EventState> $states events in any of these states; empty keeps every state
     */
    public function __construct(
        public ?string $search = null,
        public ?\DateTimeImmutable $receivedFrom = null,
        public ?\DateTimeImmutable $receivedBefore = null,
        public ?string $type = null,
        public ?int $sourceId = null,
        public array $states = [],
    ) {
    }
}
