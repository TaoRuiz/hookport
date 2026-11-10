<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Only the fields sent are changed. Source, event type and target are not listed:
 * to route differently, create a new mapping and disable this one.
 */
final readonly class MappingModificationRequest
{
    public function __construct(
        #[Assert\Range(min: 1, max: 10)]
        public ?int $maxAttempts = null,

        public ?bool $enabled = null,
    ) {
    }
}
