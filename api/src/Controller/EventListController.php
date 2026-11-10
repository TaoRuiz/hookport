<?php

namespace App\Controller;

use App\Entity\EventIn;
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
            static fn (EventIn $eventIn): array => [
                'uuid' => $eventIn->getUuid(),
                'type' => $eventIn->getType(),
                'source' => ['id' => $eventIn->getSource()->getId(), 'slug' => $eventIn->getSource()->getSlug()],
                'externalId' => $eventIn->getExternalId(),
                'receivedAt' => $eventIn->getReceivedAt(),
            ],
            $eventInRepository->findAllWithSourceNewestFirst(),
        ));
    }
}
