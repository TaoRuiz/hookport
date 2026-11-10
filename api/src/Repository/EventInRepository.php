<?php

namespace App\Repository;

use App\Entity\EventIn;
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
     * @return list<EventIn>
     */
    public function findAllWithSourceNewestFirst(): array
    {
        return $this->createQueryBuilder('eventIn')
            ->addSelect('source')
            ->innerJoin('eventIn.source', 'source')
            ->orderBy('eventIn.receivedAt', 'DESC')
            // received_at has a one-second precision; uuid v7 is time-ordered and breaks ties.
            ->addOrderBy('eventIn.uuid', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
