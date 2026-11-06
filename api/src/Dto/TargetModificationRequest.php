<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Only the fields sent are changed.
 */
final readonly class TargetModificationRequest
{
    public function __construct(
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: 255)]
        public ?string $name = null,

        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Url(requireTld: false)]
        public ?string $url = null,

        public ?bool $enabled = null,
    ) {
    }
}
