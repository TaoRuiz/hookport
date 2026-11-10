<?php

namespace App\Controller;

use App\Entity\Delivery;
use App\Entity\EventIn;
use App\Message\AttemptDelivery;
use App\Routing\EventRouter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class EventReplayController extends AbstractController
{
    /**
     * Re-runs the mapping lookup of an unrouted event, typically after adding the missing mapping.
     */
    #[Route('/api/events/{uuid}/replay', name: 'api_events_replay', requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted('ROLE_EVENT_REPLAY')]
    public function replayUnroutedEvent(
        #[MapEntity(id: 'uuid')] EventIn $eventIn,
        EventRouter $eventRouter,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
    ): JsonResponse {
        if (!$eventIn->getDeliveries()->isEmpty()) {
            return $this->json(['error' => 'This event is already routed.'], Response::HTTP_CONFLICT);
        }

        $deliveries = $eventRouter->createDeliveriesFor($eventIn);
        $entityManager->flush();

        foreach ($deliveries as $delivery) {
            $messageBus->dispatch(new AttemptDelivery($delivery->getUuid()));
        }

        return $this->json(array_map(
            static fn (Delivery $delivery): array => [
                'uuid' => $delivery->getUuid(),
                'target' => ['id' => $delivery->getTarget()->getId(), 'name' => $delivery->getTarget()->getName()],
                'status' => $delivery->getStatus(),
                'maxAttempts' => $delivery->getMaxAttempts(),
            ],
            $deliveries,
        ));
    }
}
