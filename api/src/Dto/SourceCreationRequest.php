<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SourceCreationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        #[Assert\Regex(pattern: '/^[a-z0-9]+(-[a-z0-9]+)*$/', message: 'Use lowercase letters, digits and single hyphens only.')]
        public string $slug,

        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,
    ) {
    }
}
