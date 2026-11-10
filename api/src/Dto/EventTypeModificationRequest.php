<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Only the fields sent are changed. The slug is not listed: emitters send it as the event type, so it is frozen.
 */
final readonly class EventTypeModificationRequest
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: 255)]
        public ?string $label = null,

        public ?bool $enabled = null,
    ) {
    }
}
