<?php

namespace App\Controller;

use App\Entity\Mapping;
use App\Repository\MappingRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MappingManagementController extends AbstractController
{
    #[Route('/api/mappings', name: 'api_mappings_list', methods: ['GET'])]
    public function listMappings(MappingRepository $mappingRepository): JsonResponse
    {
        $mappings = $mappingRepository->findAllWithRoutingEndpointsSortedByTargetName();

        return $this->json(array_map($this->describeMapping(...), $mappings));
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
