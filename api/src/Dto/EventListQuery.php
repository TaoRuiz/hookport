<?php

namespace App\Dto;

use App\Enum\EventState;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Every filter is optional; filters left out keep every event. Pages start at 1.
 */
final readonly class EventListQuery
{
    public const int DEFAULT_EVENTS_PER_PAGE = 25;
    public const int MAX_EVENTS_PER_PAGE = 100;

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
        #[Assert\Positive]
        public int $page = 1,
        #[Assert\Range(min: 1, max: self::MAX_EVENTS_PER_PAGE)]
        public int $perPage = self::DEFAULT_EVENTS_PER_PAGE,
    ) {
    }
}
