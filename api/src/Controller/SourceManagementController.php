<?php

namespace App\Controller;

use App\Dto\SourceCreationRequest;
use App\Entity\Source;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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

    #[Route('/api/sources', name: 'api_sources_create', methods: ['POST'])]
    #[IsGranted('ROLE_CONFIGURATION_MANAGE')]
    public function createSource(
        #[MapRequestPayload] SourceCreationRequest $sourceCreationRequest,
        SourceRepository $sourceRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        if (null !== $sourceRepository->findOneBy(['slug' => $sourceCreationRequest->slug])) {
            return $this->json(['error' => 'This slug is already used.'], Response::HTTP_CONFLICT);
        }

        $source = new Source()
            ->setSlug($sourceCreationRequest->slug)
            ->setName($sourceCreationRequest->name)
            ->setSecret(bin2hex(random_bytes(32)));
        $entityManager->persist($source);
        $entityManager->flush();

        return $this->json([
            'id' => $source->getId(),
            'slug' => $source->getSlug(),
            'name' => $source->getName(),
            'enabled' => $source->isEnabled(),
            'secret' => $source->getSecret(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/sources/{id}/secret', name: 'api_sources_show_secret', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_CONFIGURATION_MANAGE')]
    public function showSourceSecret(Source $source): JsonResponse
    {
        return $this->json(['secret' => $source->getSecret()]);
    }
}
