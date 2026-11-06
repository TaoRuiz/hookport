<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Only the fields sent are changed. The slug is not listed: emitters send it in X-Source, so it is frozen.
 */
final readonly class SourceModificationRequest
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: 255)]
        public ?string $name = null,

        public ?bool $enabled = null,
    ) {
    }
}
