<?php

namespace App\Dto;

use App\Entity\Mapping;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A null source or event type id means "any".
 */
final readonly class MappingCreationRequest
{
    public function __construct(
        public int $targetId,

        public ?int $sourceId = null,

        public ?int $eventTypeId = null,

        #[Assert\Range(min: 1, max: 10)]
        public int $maxAttempts = Mapping::DEFAULT_MAX_ATTEMPTS,
    ) {
    }
}
