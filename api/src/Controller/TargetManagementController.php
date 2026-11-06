<?php

namespace App\Controller;

use App\Entity\Target;
use App\Repository\TargetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

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
}
