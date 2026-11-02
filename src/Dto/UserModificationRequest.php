<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UserModificationRequest
{
    public function __construct(
        #[Assert\Length(min: 12)]
        public ?string $password = null,

        #[Assert\Choice(choices: ['ROLE_ADMIN', 'ROLE_DEMO'])]
        public ?string $role = null,
    ) {
    }
}
