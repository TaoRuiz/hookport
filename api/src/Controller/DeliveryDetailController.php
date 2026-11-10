<?php

namespace App\Controller;

use App\Entity\Delivery;
use App\Entity\DeliveryAttempt;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

final class DeliveryDetailController extends AbstractController
{
    #[Route('/api/deliveries/{uuid}', name: 'api_deliveries_show', requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    public function showDelivery(#[MapEntity(id: 'uuid')] Delivery $delivery): JsonResponse
    {
        return $this->json([
            'uuid' => $delivery->getUuid(),
            'eventUuid' => $delivery->getEventIn()->getUuid(),
            'target' => ['id' => $delivery->getTarget()->getId(), 'name' => $delivery->getTarget()->getName()],
            'status' => $delivery->getStatus(),
            'attemptsCount' => $delivery->getAttemptsCount(),
            'maxAttempts' => $delivery->getMaxAttempts(),
            'nextAttemptAt' => $delivery->getNextAttemptAt(),
            'attempts' => array_map(
                static fn (DeliveryAttempt $attempt): array => [
                    'attemptNo' => $attempt->getAttemptNo(),
                    'sentAt' => $attempt->getSentAt(),
                    'durationMs' => $attempt->getDurationMs(),
                    'responseCode' => $attempt->getResponseCode(),
                    'error' => $attempt->getError(),
                    'responseExcerpt' => $attempt->getResponseExcerpt(),
                ],
                $delivery->getAttempts()->toArray(),
            ),
        ]);
    }
}
