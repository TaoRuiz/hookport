<?php

namespace App\Entity;

use App\Repository\EventInRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * An event received from a source. The external id is the one given by the emitter,
 * unique per source, which is how duplicates are detected.
 */
#[ORM\Entity(repositoryClass: EventInRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_event_in_source_external_id', columns: ['source_id', 'external_id'])]
#[ORM\Index(name: 'idx_event_in_received_at', columns: ['received_at'])]
class EventIn
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $uuid;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Source $source;

    #[ORM\Column(length: 255)]
    private string $externalId;

    /**
     * Plain slug, not a foreign key to EventType: unknown types are accepted.
     */
    #[ORM\Column(length: 100)]
    private string $type;

    /**
     * @var array<mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    private array $payload;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    /**
     * @param array<mixed> $payload
     */
    public function __construct(Source $source, string $externalId, string $type, array $payload)
    {
        $this->uuid = Uuid::v7();
        $this->source = $source;
        $this->externalId = $externalId;
        $this->type = $type;
        $this->payload = $payload;
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }
}
