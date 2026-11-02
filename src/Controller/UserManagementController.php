<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER_MANAGE')]
final class UserManagementController extends AbstractController
{
    #[Route('/api/users', name: 'api_users_list', methods: ['GET'])]
    public function listUsers(UserRepository $userRepository): JsonResponse
    {
        $users = $userRepository->findBy([], ['login' => 'ASC']);

        return $this->json(array_map(
            static fn (User $user): array => [
                'id' => $user->getId(),
                'login' => $user->getLogin(),
                'roles' => $user->getRoles(),
            ],
            $users,
        ));
    }
}
