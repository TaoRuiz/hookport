<?php

namespace App\Dto;

use App\Enum\EventState;

/**
 * Every filter is optional; filters left out keep every event.
 */
final readonly class EventListQuery
{
    public function __construct(
        public ?string $search = null,
        public ?\DateTimeImmutable $receivedFrom = null,
        public ?\DateTimeImmutable $receivedBefore = null,
        public ?string $type = null,
        public ?int $sourceId = null,
        public ?EventState $state = null,
    ) {
    }
}
