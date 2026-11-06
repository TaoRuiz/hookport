<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TargetCreationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $name,

        /**
         * No top-level domain required: targets may be internal hosts (localhost, container names).
         */
        #[Assert\NotBlank]
        #[Assert\Url(requireTld: false)]
        public string $url,
    ) {
    }
}
