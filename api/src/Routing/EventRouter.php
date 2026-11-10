<?php

namespace App\Routing;

use App\Entity\Delivery;
use App\Entity\EventIn;
use App\Entity\Mapping;
use App\Repository\MappingRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class EventRouter
{
    public function __construct(
        private MappingRepository $mappingRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Persists one pending delivery per distinct matching target. The caller flushes,
     * so the event and its deliveries are stored together.
     *
     * @return list<Delivery>
     */
    public function createDeliveriesFor(EventIn $eventIn): array
    {
        $matchingMappings = $this->mappingRepository->findEnabledMappingsMatching($eventIn->getSource(), $eventIn->getType());

        $deliveries = [];
        foreach ($this->keepWinningMappingPerTarget($matchingMappings) as $winningMapping) {
            $delivery = new Delivery($eventIn, $winningMapping->getTarget(), $winningMapping->getMaxAttempts());
            $this->entityManager->persist($delivery);
            $deliveries[] = $delivery;
        }

        return $deliveries;
    }

    /**
     * @param list<Mapping> $mappings
     *
     * @return list<Mapping>
     */
    private function keepWinningMappingPerTarget(array $mappings): array
    {
        $winningMappingByTargetId = [];
        foreach ($mappings as $mapping) {
            $targetId = $mapping->getTarget()->getId();
            $currentWinner = $winningMappingByTargetId[$targetId] ?? null;
            if (null === $currentWinner || $this->outranks($mapping, $currentWinner)) {
                $winningMappingByTargetId[$targetId] = $mapping;
            }
        }

        return array_values($winningMappingByTargetId);
    }

    /**
     * The most specific mapping wins. Equal specificity can only be "source only" against
     * "event type only" (identical mappings are refused), then the highest max attempts wins.
     */
    private function outranks(Mapping $candidate, Mapping $currentWinner): bool
    {
        $specificityDifference = $this->countSpecifiedCriteria($candidate) - $this->countSpecifiedCriteria($currentWinner);
        if (0 !== $specificityDifference) {
            return $specificityDifference > 0;
        }

        return $candidate->getMaxAttempts() > $currentWinner->getMaxAttempts();
    }

    private function countSpecifiedCriteria(Mapping $mapping): int
    {
        return (null !== $mapping->getSource() ? 1 : 0) + (null !== $mapping->getEventType() ? 1 : 0);
    }
}
