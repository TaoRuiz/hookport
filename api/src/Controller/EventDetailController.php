<?php

namespace App\Controller;

use App\Entity\Delivery;
use App\Entity\EventIn;
use App\Enum\DeliveryStatus;
use App\Enum\EventState;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class EventDetailController extends AbstractController
{
    #[Route('/api/events/{uuid}', name: 'api_events_show', requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    public function showEvent(#[MapEntity(id: 'uuid')] EventIn $eventIn): JsonResponse
    {
        $deliveries = $eventIn->getDeliveries()->toArray();

        return $this->json([
            'uuid' => $eventIn->getUuid(),
            'type' => $eventIn->getType(),
            'source' => ['id' => $eventIn->getSource()->getId(), 'slug' => $eventIn->getSource()->getSlug()],
            'externalId' => $eventIn->getExternalId(),
            'receivedAt' => $eventIn->getReceivedAt(),
            'payload' => $eventIn->getPayload(),
            'state' => $this->deriveEventState($deliveries),
            'deliveries' => array_map(
                static fn (Delivery $delivery): array => [
                    'uuid' => $delivery->getUuid(),
                    'target' => ['id' => $delivery->getTarget()->getId(), 'name' => $delivery->getTarget()->getName()],
                    'status' => $delivery->getStatus(),
                    'attemptsCount' => $delivery->getAttemptsCount(),
                    'maxAttempts' => $delivery->getMaxAttempts(),
                    'nextAttemptAt' => $delivery->getNextAttemptAt(),
                ],
                $deliveries,
            ),
        ]);
    }

    /**
     * @param list<Delivery> $deliveries
     */
    private function deriveEventState(array $deliveries): EventState
    {
        $countDeliveriesWithStatus = static fn (DeliveryStatus ...$statuses): int => count(array_filter(
            $deliveries,
            static fn (Delivery $delivery): bool => in_array($delivery->getStatus(), $statuses, true),
        ));

        return EventState::fromDeliveryCounts(
            count($deliveries),
            $countDeliveriesWithStatus(DeliveryStatus::Failed),
            $countDeliveriesWithStatus(DeliveryStatus::Pending, DeliveryStatus::Retrying),
        );
    }
}
