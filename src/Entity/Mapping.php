<?php

namespace App\Entity;

use App\Repository\MappingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Routing rule: events matching (source, event type) go to the target.
 * A null source or event type is a wildcard ("any").
 */
#[ORM\Entity(repositoryClass: MappingRepository::class)]
class Mapping
{
    public const int DEFAULT_MAX_ATTEMPTS = 5;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Source $source = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?EventType $eventType = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Target $target = null;

    #[ORM\Column(options: ['default' => self::DEFAULT_MAX_ATTEMPTS])]
    private int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): ?Source
    {
        return $this->source;
    }

    public function setSource(?Source $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getEventType(): ?EventType
    {
        return $this->eventType;
    }

    public function setEventType(?EventType $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getTarget(): ?Target
    {
        return $this->target;
    }

    public function setTarget(Target $target): static
    {
        $this->target = $target;

        return $this;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function setMaxAttempts(int $maxAttempts): static
    {
        $this->maxAttempts = $maxAttempts;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }
}
