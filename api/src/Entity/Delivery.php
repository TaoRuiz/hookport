<?php

namespace App\Entity;

use App\Enum\DeliveryStatus;
use App\Repository\DeliveryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * One event to one target, holding the current delivery state.
 *
 * max_attempts is a snapshot copied from the mapping at creation, so editing the mapping
 * does not change deliveries already in flight. The target is referenced directly (not
 * snapshotted), so fixing a target URL benefits retries and replays.
 */
#[ORM\Entity(repositoryClass: DeliveryRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_delivery_event_in_target', columns: ['event_in_id', 'target_id'])]
#[ORM\Index(name: 'idx_delivery_status_next_attempt_at', columns: ['status', 'next_attempt_at'])]
class Delivery
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $uuid;

    #[ORM\ManyToOne(inversedBy: 'deliveries')]
    #[ORM\JoinColumn(referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private EventIn $eventIn;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Target $target;

    #[ORM\Column(length: 20, enumType: DeliveryStatus::class)]
    private DeliveryStatus $status = DeliveryStatus::Pending;

    #[ORM\Column]
    private int $attemptsCount = 0;

    #[ORM\Column]
    private int $maxAttempts;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $nextAttemptAt = null;

    /**
     * @var Collection<int, DeliveryAttempt>
     */
    #[ORM\OneToMany(targetEntity: DeliveryAttempt::class, mappedBy: 'delivery')]
    #[ORM\OrderBy(['attemptNo' => 'ASC'])]
    private Collection $attempts;

    public function __construct(EventIn $eventIn, Target $target, int $maxAttempts)
    {
        $this->uuid = Uuid::v7();
        $this->eventIn = $eventIn;
        $this->target = $target;
        $this->maxAttempts = $maxAttempts;
        $this->attempts = new ArrayCollection();
    }

    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    public function getEventIn(): EventIn
    {
        return $this->eventIn;
    }

    public function getTarget(): Target
    {
        return $this->target;
    }

    public function getStatus(): DeliveryStatus
    {
        return $this->status;
    }

    public function setStatus(DeliveryStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getAttemptsCount(): int
    {
        return $this->attemptsCount;
    }

    public function setAttemptsCount(int $attemptsCount): static
    {
        $this->attemptsCount = $attemptsCount;

        return $this;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getNextAttemptAt(): ?\DateTimeImmutable
    {
        return $this->nextAttemptAt;
    }

    public function setNextAttemptAt(?\DateTimeImmutable $nextAttemptAt): static
    {
        $this->nextAttemptAt = $nextAttemptAt;

        return $this;
    }

    /**
     * @return Collection<int, DeliveryAttempt>
     */
    public function getAttempts(): Collection
    {
        return $this->attempts;
    }
}
