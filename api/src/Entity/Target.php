<?php

namespace App\Entity;

use App\Enum\TargetProtocol;
use App\Repository\TargetRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A destination events are delivered to. The protocol picks the transport,
 * the config holds protocol-specific settings (v1, http: { "url": ... }).
 */
#[ORM\Entity(repositoryClass: TargetRepository::class)]
class Target
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 20, enumType: TargetProtocol::class)]
    private TargetProtocol $protocol = TargetProtocol::Http;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column]
    private array $config = [];

    /**
     * Used to sign outgoing requests, so it is stored readable (not hashed).
     */
    #[ORM\Column(length: 255)]
    private ?string $secret = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getProtocol(): TargetProtocol
    {
        return $this->protocol;
    }

    public function setProtocol(TargetProtocol $protocol): static
    {
        $this->protocol = $protocol;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): static
    {
        $this->config = $config;

        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): static
    {
        $this->secret = $secret;

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
