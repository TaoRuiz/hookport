<?php

namespace App\Controller;

use App\Dto\EventListQuery;
use App\Entity\Delivery;
use App\Entity\EventIn;
use App\Enum\EventState;
use App\Repository\DeliveryRepository;
use App\Repository\EventInRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

final class EventListController extends AbstractController
{
    #[Route('/api/events', name: 'api_events_list', methods: ['GET'])]
    public function listEvents(
        EventInRepository $eventInRepository,
        DeliveryRepository $deliveryRepository,
        #[MapQueryString(validationFailedStatusCode: 422)] EventListQuery $eventListQuery = new EventListQuery(),
    ): JsonResponse {
        $eventRows = $eventInRepository->findPageWithSourceTypeLabelAndDeliveryCountsNewestFirst($eventListQuery);
        $deliveriesByEventUuid = $deliveryRepository->findWithTargetGroupedByEventUuid(array_column($eventRows, 0));

        return $this->json([
            'events' => array_map(
                fn (array $eventRow): array => $this->describeEventRow($eventRow, $deliveriesByEventUuid[(string) $eventRow[0]->getUuid()] ?? []),
                $eventRows,
            ),
            'totalCount' => $eventInRepository->countEventsMatching($eventListQuery),
        ]);
    }

    /**
     * @param array{0: EventIn, eventTypeLabel: ?string, deliveriesCount: int|string, failedDeliveriesCount: int|string|null, ongoingDeliveriesCount: int|string|null} $eventRow
     * @param list<Delivery>                                                                                                                                           $deliveries
     *
     * @return array<string, mixed>
     */
    private function describeEventRow(array $eventRow, array $deliveries): array
    {
        $eventIn = $eventRow[0];

        return [
            'uuid' => $eventIn->getUuid(),
            'type' => ['slug' => $eventIn->getType(), 'label' => $eventRow['eventTypeLabel']],
            'source' => [
                'id' => $eventIn->getSource()->getId(),
                'slug' => $eventIn->getSource()->getSlug(),
                'name' => $eventIn->getSource()->getName(),
            ],
            'externalId' => $eventIn->getExternalId(),
            'receivedAt' => $eventIn->getReceivedAt(),
            'state' => EventState::fromDeliveryCounts(
                (int) $eventRow['deliveriesCount'],
                (int) $eventRow['failedDeliveriesCount'],
                (int) $eventRow['ongoingDeliveriesCount'],
            ),
            'deliveries' => array_map(
                static fn (Delivery $delivery): array => [
                    'uuid' => $delivery->getUuid(),
                    'target' => ['id' => $delivery->getTarget()->getId(), 'name' => $delivery->getTarget()->getName()],
                    'status' => $delivery->getStatus(),
                ],
                $deliveries,
            ),
        ];
    }
}
