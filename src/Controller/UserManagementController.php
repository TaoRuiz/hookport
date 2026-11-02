<?php

namespace App\Controller;

use App\Dto\UserCreationRequest;
use App\Dto\UserModificationRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
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

    #[Route('/api/users', name: 'api_users_create', methods: ['POST'])]
    public function createUser(
        #[MapRequestPayload] UserCreationRequest $userCreationRequest,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        if (null !== $userRepository->findOneBy(['login' => $userCreationRequest->login])) {
            return $this->json(['error' => 'This login is already used.'], Response::HTTP_CONFLICT);
        }

        $user = new User($userCreationRequest->login);
        $user->setRoles([$userCreationRequest->role]);
        $user->setPassword($passwordHasher->hashPassword($user, $userCreationRequest->password));
        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json([
            'id' => $user->getId(),
            'login' => $user->getLogin(),
            'roles' => $user->getRoles(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/users/{id}', name: 'api_users_modify', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function modifyUser(
        User $user,
        #[MapRequestPayload] UserModificationRequest $userModificationRequest,
        #[CurrentUser] User $authenticatedUser,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $isChangingOwnRole = null !== $userModificationRequest->role && $user->getId() === $authenticatedUser->getId();
        if ($isChangingOwnRole) {
            return $this->json(['error' => 'You cannot change your own role.'], Response::HTTP_FORBIDDEN);
        }

        if (null !== $userModificationRequest->role) {
            $user->setRoles([$userModificationRequest->role]);
        }

        if (null !== $userModificationRequest->password) {
            $user->setPassword($passwordHasher->hashPassword($user, $userModificationRequest->password));
        }

        $entityManager->flush();

        return $this->json([
            'id' => $user->getId(),
            'login' => $user->getLogin(),
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route('/api/users/{id}', name: 'api_users_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteUser(
        User $user,
        #[CurrentUser] User $authenticatedUser,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($user->getId() === $authenticatedUser->getId()) {
            return $this->json(['error' => 'You cannot delete your own account.'], Response::HTTP_FORBIDDEN);
        }

        $entityManager->remove($user);
        $entityManager->flush();

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
