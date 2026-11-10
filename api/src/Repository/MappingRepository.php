<?php

namespace App\Repository;

use App\Entity\EventType;
use App\Entity\Mapping;
use App\Entity\Source;
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

    /**
     * Enabled mappings whose source and event type match, a null one meaning "any".
     * A disabled event type is not routed at all, not even through "any event type" mappings;
     * a type missing from the catalog still goes through them.
     *
     * @return list<Mapping>
     */
    public function findEnabledMappingsMatching(Source $source, string $eventTypeSlug): array
    {
        return $this->createQueryBuilder('mapping')
            ->addSelect('target')
            ->innerJoin('mapping.target', 'target')
            ->leftJoin('mapping.eventType', 'eventType')
            ->where('mapping.enabled = true')
            ->andWhere('mapping.source IS NULL OR mapping.source = :source')
            ->andWhere('mapping.eventType IS NULL OR eventType.slug = :eventTypeSlug')
            ->andWhere(sprintf(
                'NOT EXISTS (SELECT disabledEventType.id FROM %s disabledEventType WHERE disabledEventType.slug = :eventTypeSlug AND disabledEventType.enabled = false)',
                EventType::class,
            ))
            ->setParameter('source', $source)
            ->setParameter('eventTypeSlug', $eventTypeSlug)
            ->getQuery()
            ->getResult();
    }
}
