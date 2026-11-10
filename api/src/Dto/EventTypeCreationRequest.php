<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class EventTypeCreationRequest
{
    public function __construct(
        // Permissive on purpose: the slug must equal the type sent by any emitter, whatever its naming style.
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        #[Assert\Regex(pattern: '/^\S+$/', message: 'The slug cannot contain spaces.')]
        public string $slug,

        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $label,
    ) {
    }
}
