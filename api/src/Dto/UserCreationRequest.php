<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UserCreationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 180)]
        public string $login,

        #[Assert\NotBlank]
        #[Assert\Length(min: 12)]
        public string $password,

        #[Assert\Choice(choices: ['ROLE_ADMIN', 'ROLE_DEMO'])]
        public string $role,
    ) {
    }
}
