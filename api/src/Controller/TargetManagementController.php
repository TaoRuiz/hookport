<?php

namespace App\Controller;

use App\Dto\TargetCreationRequest;
use App\Entity\Target;
use App\Repository\TargetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class TargetManagementController extends AbstractController
{
    #[Route('/api/targets', name: 'api_targets_list', methods: ['GET'])]
    public function listTargets(TargetRepository $targetRepository): JsonResponse
    {
        $targets = $targetRepository->findBy([], ['name' => 'ASC']);

        return $this->json(array_map(
            static fn (Target $target): array => [
                'id' => $target->getId(),
                'name' => $target->getName(),
                'protocol' => $target->getProtocol(),
                'config' => $target->getConfig(),
                'enabled' => $target->isEnabled(),
            ],
            $targets,
        ));
    }

    #[Route('/api/targets', name: 'api_targets_create', methods: ['POST'])]
    #[IsGranted('ROLE_CONFIGURATION_MANAGE')]
    public function createTarget(
        #[MapRequestPayload] TargetCreationRequest $targetCreationRequest,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $target = new Target()
            ->setName($targetCreationRequest->name)
            ->setConfig(['url' => $targetCreationRequest->url])
            ->setSecret(bin2hex(random_bytes(32)));
        $entityManager->persist($target);
        $entityManager->flush();

        return $this->json([
            'id' => $target->getId(),
            'name' => $target->getName(),
            'protocol' => $target->getProtocol(),
            'config' => $target->getConfig(),
            'enabled' => $target->isEnabled(),
            'secret' => $target->getSecret(),
        ], Response::HTTP_CREATED);
    }
}
