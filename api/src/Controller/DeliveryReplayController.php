<?php

namespace App\Controller;

use App\Entity\Delivery;
use App\Enum\DeliveryStatus;
use App\Message\AttemptDelivery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class DeliveryReplayController extends AbstractController
{
    /**
     * Queues one more attempt of a failed delivery, typically after fixing or re-enabling its target.
     * The attempt count keeps growing, so a replay gets a single attempt once max attempts is reached.
     */
    #[Route('/api/deliveries/{uuid}/replay', name: 'api_deliveries_replay', requirements: ['uuid' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted('ROLE_EVENT_REPLAY')]
    public function replayFailedDelivery(
        #[MapEntity(id: 'uuid')] Delivery $delivery,
        EntityManagerInterface $entityManager,
        MessageBusInterface $messageBus,
    ): Response {
        if (DeliveryStatus::Failed !== $delivery->getStatus()) {
            return new JsonResponse(['error' => 'Only a failed delivery can be replayed.'], Response::HTTP_CONFLICT);
        }

        $delivery
            ->setStatus(DeliveryStatus::Pending)
            ->setNextAttemptAt(null);
        $entityManager->flush();

        $messageBus->dispatch(new AttemptDelivery($delivery->getUuid()));

        return new Response(status: Response::HTTP_ACCEPTED);
    }
}
