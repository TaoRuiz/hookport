<?php

namespace App\Repository;

use App\Entity\Delivery;
use App\Entity\EventIn;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Delivery>
 */
class DeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Delivery::class);
    }

    /**
     * One query for a whole list of events, so listing them never loads deliveries event by event.
     *
     * @param list<EventIn> $eventIns
     *
     * @return array<string, list<Delivery>> deliveries ordered by target name, keyed by event uuid
     */
    public function findWithTargetGroupedByEventUuid(array $eventIns): array
    {
        if ([] === $eventIns) {
            return [];
        }

        $deliveries = $this->createQueryBuilder('delivery')
            ->addSelect('target')
            ->innerJoin('delivery.target', 'target')
            ->where('delivery.eventIn IN (:eventIns)')
            ->orderBy('target.name')
            ->setParameter('eventIns', $eventIns)
            ->getQuery()
            ->getResult();

        $deliveriesByEventUuid = [];
        foreach ($deliveries as $delivery) {
            $deliveriesByEventUuid[(string) $delivery->getEventIn()->getUuid()][] = $delivery;
        }

        return $deliveriesByEventUuid;
    }
}
