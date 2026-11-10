<?php

namespace App\Controller;

use App\Dto\MappingCreationRequest;
use App\Entity\Mapping;
use App\Repository\EventTypeRepository;
use App\Repository\MappingRepository;
use App\Repository\SourceRepository;
use App\Repository\TargetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class MappingManagementController extends AbstractController
{
    #[Route('/api/mappings', name: 'api_mappings_list', methods: ['GET'])]
    public function listMappings(MappingRepository $mappingRepository): JsonResponse
    {
        $mappings = $mappingRepository->findAllWithRoutingEndpointsSortedByTargetName();

        return $this->json(array_map($this->describeMapping(...), $mappings));
    }

    #[Route('/api/mappings', name: 'api_mappings_create', methods: ['POST'])]
    #[IsGranted('ROLE_CONFIGURATION_MANAGE')]
    public function createMapping(
        #[MapRequestPayload] MappingCreationRequest $mappingCreationRequest,
        SourceRepository $sourceRepository,
        EventTypeRepository $eventTypeRepository,
        TargetRepository $targetRepository,
        MappingRepository $mappingRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $target = $targetRepository->find($mappingCreationRequest->targetId);
        if (null === $target) {
            return $this->json(['error' => 'Unknown target.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $source = null;
        if (null !== $mappingCreationRequest->sourceId) {
            $source = $sourceRepository->find($mappingCreationRequest->sourceId);
            if (null === $source) {
                return $this->json(['error' => 'Unknown source.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $eventType = null;
        if (null !== $mappingCreationRequest->eventTypeId) {
            $eventType = $eventTypeRepository->find($mappingCreationRequest->eventTypeId);
            if (null === $eventType) {
                return $this->json(['error' => 'Unknown event type.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // findOneBy turns a null criterion into IS NULL, so wildcard duplicates are found too.
        if (null !== $mappingRepository->findOneBy(['source' => $source, 'eventType' => $eventType, 'target' => $target])) {
            return $this->json(['error' => 'This mapping already exists.'], Response::HTTP_CONFLICT);
        }

        $mapping = new Mapping()
            ->setSource($source)
            ->setEventType($eventType)
            ->setTarget($target)
            ->setMaxAttempts($mappingCreationRequest->maxAttempts);
        $entityManager->persist($mapping);
        $entityManager->flush();

        return $this->json($this->describeMapping($mapping), Response::HTTP_CREATED);
    }

    /**
     * A null source or event type means "any".
     *
     * @return array<string, mixed>
     */
    private function describeMapping(Mapping $mapping): array
    {
        $source = $mapping->getSource();
        $eventType = $mapping->getEventType();
        $target = $mapping->getTarget();

        return [
            'id' => $mapping->getId(),
            'source' => null === $source ? null : ['id' => $source->getId(), 'slug' => $source->getSlug(), 'name' => $source->getName()],
            'eventType' => null === $eventType ? null : ['id' => $eventType->getId(), 'slug' => $eventType->getSlug(), 'label' => $eventType->getLabel()],
            'target' => ['id' => $target->getId(), 'name' => $target->getName()],
            'maxAttempts' => $mapping->getMaxAttempts(),
            'enabled' => $mapping->isEnabled(),
        ];
    }
}
