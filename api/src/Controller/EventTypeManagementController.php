<?php

namespace App\Controller;

use App\Entity\EventType;
use App\Repository\EventTypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class EventTypeManagementController extends AbstractController
{
    #[Route('/api/event-types', name: 'api_event_types_list', methods: ['GET'])]
    public function listEventTypes(EventTypeRepository $eventTypeRepository): JsonResponse
    {
        $eventTypes = $eventTypeRepository->findBy([], ['label' => 'ASC']);

        return $this->json(array_map(
            static fn (EventType $eventType): array => [
                'id' => $eventType->getId(),
                'slug' => $eventType->getSlug(),
                'label' => $eventType->getLabel(),
                'enabled' => $eventType->isEnabled(),
            ],
            $eventTypes,
        ));
    }
}
