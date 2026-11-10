<?php

namespace App\Repository;

use App\Entity\EventIn;
use App\Enum\DeliveryStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventIn>
 */
class EventInRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventIn::class);
    }

    /**
     * Counts are aggregated in the query so the list never loads every delivery.
     *
     * @return list<array{0: EventIn, deliveriesCount: int|string, failedDeliveriesCount: int|string|null, ongoingDeliveriesCount: int|string|null}>
     */
    public function findAllWithSourceAndDeliveryCountsNewestFirst(): array
    {
        return $this->createQueryBuilder('eventIn')
            ->addSelect('source')
            ->addSelect('COUNT(delivery.uuid) AS deliveriesCount')
            ->addSelect('SUM(CASE WHEN delivery.status = :failedStatus THEN 1 ELSE 0 END) AS failedDeliveriesCount')
            ->addSelect('SUM(CASE WHEN delivery.status IN (:ongoingStatuses) THEN 1 ELSE 0 END) AS ongoingDeliveriesCount')
            ->innerJoin('eventIn.source', 'source')
            ->leftJoin('eventIn.deliveries', 'delivery')
            ->groupBy('eventIn.uuid', 'source.id')
            ->orderBy('eventIn.receivedAt', 'DESC')
            // received_at has a one-second precision; uuid v7 is time-ordered and breaks ties.
            ->addOrderBy('eventIn.uuid', 'DESC')
            ->setParameter('failedStatus', DeliveryStatus::Failed->value)
            ->setParameter('ongoingStatuses', [DeliveryStatus::Pending->value, DeliveryStatus::Retrying->value])
            ->getQuery()
            ->getResult();
    }
}
