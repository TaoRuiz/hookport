<?php

namespace App\Entity;

use App\Repository\DeliveryAttemptRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Log of one try of a delivery. Immutable once recorded.
 * response_code is null when there was no response (timeout, connection error).
 */
#[ORM\Entity(repositoryClass: DeliveryAttemptRepository::class)]
class DeliveryAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attempts')]
    #[ORM\JoinColumn(referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private Delivery $delivery;

    #[ORM\Column]
    private int $attemptNo;

    #[ORM\Column]
    private \DateTimeImmutable $sentAt;

    #[ORM\Column]
    private int $durationMs;

    #[ORM\Column(nullable: true)]
    private ?int $responseCode;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $error;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $responseExcerpt;

    public function __construct(
        Delivery $delivery,
        int $attemptNo,
        \DateTimeImmutable $sentAt,
        int $durationMs,
        ?int $responseCode,
        ?string $error,
        ?string $responseExcerpt,
    ) {
        $this->delivery = $delivery;
        $this->attemptNo = $attemptNo;
        $this->sentAt = $sentAt;
        $this->durationMs = $durationMs;
        $this->responseCode = $responseCode;
        $this->error = $error;
        $this->responseExcerpt = $responseExcerpt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDelivery(): Delivery
    {
        return $this->delivery;
    }

    public function getAttemptNo(): int
    {
        return $this->attemptNo;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function getResponseCode(): ?int
    {
        return $this->responseCode;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getResponseExcerpt(): ?string
    {
        return $this->responseExcerpt;
    }
}
