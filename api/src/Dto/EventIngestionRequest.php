<?php

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class EventIngestionRequest
{
    /**
     * @param array<mixed> $payload
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public string $type,

        #[SerializedName('id')]
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public string $externalId,

        public array $payload,
    ) {
    }
}
