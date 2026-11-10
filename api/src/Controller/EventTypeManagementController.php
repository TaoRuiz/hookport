<?php

namespace App\Controller;

use App\Dto\EventTypeCreationRequest;
use App\Dto\EventTypeModificationRequest;
use App\Entity\EventType;
use App\Repository\EventTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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

    #[Route('/api/event-types', name: 'api_event_types_create', methods: ['POST'])]
    #[IsGranted('ROLE_CONFIGURATION_MANAGE')]
    public function createEventType(
        #[MapRequestPayload] EventTypeCreationRequest $eventTypeCreationRequest,
        EventTypeRepository $eventTypeRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        if (null !== $eventTypeRepository->findOneBy(['slug' => $eventTypeCreationRequest->slug])) {
            return $this->json(['error' => 'This slug is already used.'], Response::HTTP_CONFLICT);
        }

        $eventType = new EventType($eventTypeCreationRequest->slug)
            ->setLabel($eventTypeCreationRequest->label);
        $entityManager->persist($eventType);
        $entityManager->flush();

        return $this->json([
            'id' => $eventType->getId(),
            'slug' => $eventType->getSlug(),
            'label' => $eventType->getLabel(),
            'enabled' => $eventType->isEnabled(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/event-types/{id}', name: 'api_event_types_modify', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[IsGranted('ROLE_CONFIGURATION_MANAGE')]
    public function modifyEventType(
        EventType $eventType,
        #[MapRequestPayload] EventTypeModificationRequest $eventTypeModificationRequest,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        if (null !== $eventTypeModificationRequest->label) {
            $eventType->setLabel($eventTypeModificationRequest->label);
        }

        if (null !== $eventTypeModificationRequest->enabled) {
            $eventType->setEnabled($eventTypeModificationRequest->enabled);
        }

        $entityManager->flush();

        return $this->json([
            'id' => $eventType->getId(),
            'slug' => $eventType->getSlug(),
            'label' => $eventType->getLabel(),
            'enabled' => $eventType->isEnabled(),
        ]);
    }
}
