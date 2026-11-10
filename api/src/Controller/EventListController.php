<?php

namespace App\Controller;

use App\Enum\EventState;
use App\Repository\EventInRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class EventListController extends AbstractController
{
    #[Route('/api/events', name: 'api_events_list', methods: ['GET'])]
    public function listEvents(EventInRepository $eventInRepository): JsonResponse
    {
        return $this->json(array_map(
            static fn (array $eventRow): array => [
                'uuid' => $eventRow[0]->getUuid(),
                'type' => $eventRow[0]->getType(),
                'source' => ['id' => $eventRow[0]->getSource()->getId(), 'slug' => $eventRow[0]->getSource()->getSlug()],
                'externalId' => $eventRow[0]->getExternalId(),
                'receivedAt' => $eventRow[0]->getReceivedAt(),
                'state' => EventState::fromDeliveryCounts(
                    (int) $eventRow['deliveriesCount'],
                    (int) $eventRow['failedDeliveriesCount'],
                    (int) $eventRow['ongoingDeliveriesCount'],
                ),
            ],
            $eventInRepository->findAllWithSourceAndDeliveryCountsNewestFirst(),
        ));
    }
}
