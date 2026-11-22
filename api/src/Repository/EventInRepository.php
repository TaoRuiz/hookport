<?php

namespace App\Repository;

use App\Dto\EventListQuery;
use App\Entity\EventIn;
use App\Enum\DeliveryStatus;
use App\Enum\EventState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    private const string DELIVERIES_COUNT = 'COUNT(delivery.uuid)';
    private const string FAILED_DELIVERIES_COUNT = 'SUM(CASE WHEN delivery.status = :failedStatus THEN 1 ELSE 0 END)';
    private const string ONGOING_DELIVERIES_COUNT = 'SUM(CASE WHEN delivery.status IN (:ongoingStatuses) THEN 1 ELSE 0 END)';

    /**
     * Counts are aggregated in the query so the list never loads every delivery.
     * The search keeps the events whose external id, type or payload contains the text, ignoring case.
     *
     * @return list<array{0: EventIn, deliveriesCount: int|string, failedDeliveriesCount: int|string|null, ongoingDeliveriesCount: int|string|null}>
     */
    public function findWithSourceAndDeliveryCountsNewestFirst(EventListQuery $eventListQuery): array
    {
        $queryBuilder = $this->createQueryBuilder('eventIn')
            ->addSelect('source')
            ->addSelect(self::DELIVERIES_COUNT.' AS deliveriesCount')
            ->addSelect(self::FAILED_DELIVERIES_COUNT.' AS failedDeliveriesCount')
            ->addSelect(self::ONGOING_DELIVERIES_COUNT.' AS ongoingDeliveriesCount')
            ->innerJoin('eventIn.source', 'source')
            ->leftJoin('eventIn.deliveries', 'delivery')
            ->groupBy('eventIn.uuid', 'source.id')
            ->orderBy('eventIn.receivedAt', 'DESC')
            // received_at has a one-second precision; uuid v7 is time-ordered and breaks ties.
            ->addOrderBy('eventIn.uuid', 'DESC')
            ->setParameter('failedStatus', DeliveryStatus::Failed->value)
            ->setParameter('ongoingStatuses', [DeliveryStatus::Pending->value, DeliveryStatus::Retrying->value]);

        $this->keepEventsContainingSearchedText($queryBuilder, $eventListQuery->search);
        $this->keepEventsMatchingFields($queryBuilder, $eventListQuery);
        if ([] !== $eventListQuery->states) {
            $queryBuilder->andHaving($queryBuilder->expr()->orX(
                ...array_map($this->buildEventStateCondition(...), $eventListQuery->states),
            ));
        }

        return $queryBuilder->getQuery()->getResult();
    }

    private function keepEventsContainingSearchedText(QueryBuilder $queryBuilder, ?string $searchedText): void
    {
        if (null === $searchedText || '' === trim($searchedText)) {
            return;
        }

        $queryBuilder
            ->andWhere('LOWER(eventIn.externalId) LIKE :searchPattern OR LOWER(eventIn.type) LIKE :searchPattern OR LOWER(JSONB_AS_TEXT(eventIn.payload)) LIKE :searchPattern')
            // Escapes LIKE wildcards so "%" or "_" typed by the user are matched literally.
            ->setParameter('searchPattern', '%'.addcslashes(mb_strtolower(trim($searchedText)), '%_\\').'%');
    }

    private function keepEventsMatchingFields(QueryBuilder $queryBuilder, EventListQuery $eventListQuery): void
    {
        if (null !== $eventListQuery->receivedFrom) {
            $queryBuilder->andWhere('eventIn.receivedAt >= :receivedFrom')->setParameter('receivedFrom', $this->convertToStorageTimezone($eventListQuery->receivedFrom));
        }
        if (null !== $eventListQuery->receivedBefore) {
            $queryBuilder->andWhere('eventIn.receivedAt < :receivedBefore')->setParameter('receivedBefore', $this->convertToStorageTimezone($eventListQuery->receivedBefore));
        }
        if (null !== $eventListQuery->type) {
            $queryBuilder->andWhere('eventIn.type = :type')->setParameter('type', $eventListQuery->type);
        }
        if (null !== $eventListQuery->sourceId) {
            $queryBuilder->andWhere('source.id = :sourceId')->setParameter('sourceId', $eventListQuery->sourceId);
        }
    }

    /**
     * received_at is stored without time zone, in the PHP default one, and Doctrine formats a date without converting it.
     */
    private function convertToStorageTimezone(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }

    /**
     * Mirrors EventState::fromDeliveryCounts in SQL, so filtering happens in the database: keep both rules in sync.
     */
    private function buildEventStateCondition(EventState $eventState): string
    {
        $hasDeliveriesAndNoneFailed = self::DELIVERIES_COUNT.' > 0 AND '.self::FAILED_DELIVERIES_COUNT.' = 0';

        return match ($eventState) {
            EventState::Unrouted => self::DELIVERIES_COUNT.' = 0',
            EventState::NeedsAttention => self::FAILED_DELIVERIES_COUNT.' > 0',
            EventState::InProgress => $hasDeliveriesAndNoneFailed.' AND '.self::ONGOING_DELIVERIES_COUNT.' > 0',
            EventState::Delivered => $hasDeliveriesAndNoneFailed.' AND '.self::ONGOING_DELIVERIES_COUNT.' = 0',
        };
    }
}
