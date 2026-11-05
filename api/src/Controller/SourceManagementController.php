<?php

namespace App\Controller;

use App\Entity\Source;
use App\Repository\SourceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class SourceManagementController extends AbstractController
{
    #[Route('/api/sources', name: 'api_sources_list', methods: ['GET'])]
    public function listSources(SourceRepository $sourceRepository): JsonResponse
    {
        $sources = $sourceRepository->findBy([], ['name' => 'ASC']);

        return $this->json(array_map(
            static fn (Source $source): array => [
                'id' => $source->getId(),
                'slug' => $source->getSlug(),
                'name' => $source->getName(),
                'enabled' => $source->isEnabled(),
            ],
            $sources,
        ));
    }
}
