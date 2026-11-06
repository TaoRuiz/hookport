<?php

namespace App\Controller;

use App\Dto\TargetCreationRequest;
use App\Dto\TargetModificationRequest;
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

        return $this->json(array_map($this->describeTargetWithoutSecret(...), $targets));
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

        return $this->json(
            [...$this->describeTargetWithoutSecret($target), 'secret' => $target->getSecret()],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/api/targets/{id}/secret', name: 'api_targets_show_secret', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_CONFIGURATION_MANAGE')]
    public function showTargetSecret(Target $target): JsonResponse
    {
        return $this->json(['secret' => $target->getSecret()]);
    }

    #[Route('/api/targets/{id}', name: 'api_targets_modify', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[IsGranted('ROLE_CONFIGURATION_MANAGE')]
    public function modifyTarget(
        Target $target,
        #[MapRequestPayload] TargetModificationRequest $targetModificationRequest,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        if (null !== $targetModificationRequest->name) {
            $target->setName($targetModificationRequest->name);
        }

        if (null !== $targetModificationRequest->url) {
            $target->setConfig(['url' => $targetModificationRequest->url]);
        }

        if (null !== $targetModificationRequest->enabled) {
            $target->setEnabled($targetModificationRequest->enabled);
        }

        $entityManager->flush();

        return $this->json($this->describeTargetWithoutSecret($target));
    }

    /**
     * @return array<string, mixed>
     */
    private function describeTargetWithoutSecret(Target $target): array
    {
        return [
            'id' => $target->getId(),
            'name' => $target->getName(),
            'protocol' => $target->getProtocol(),
            'config' => $target->getConfig(),
            'enabled' => $target->isEnabled(),
        ];
    }
}
