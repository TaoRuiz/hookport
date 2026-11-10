<?php

namespace App\Repository;

use App\Entity\Mapping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Mapping>
 */
class MappingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Mapping::class);
    }

    /**
     * Joins source, event type and target in the same query, grouped by target name.
     *
     * @return list<Mapping>
     */
    public function findAllWithRoutingEndpointsSortedByTargetName(): array
    {
        return $this->createQueryBuilder('mapping')
            ->addSelect('source', 'eventType', 'target')
            ->leftJoin('mapping.source', 'source')
            ->leftJoin('mapping.eventType', 'eventType')
            ->innerJoin('mapping.target', 'target')
            ->orderBy('target.name', 'ASC')
            ->addOrderBy('mapping.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
