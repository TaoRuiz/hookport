<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class LoginController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function describeAuthenticatedUser(#[CurrentUser] User $authenticatedUser): JsonResponse
    {
        return $this->json([
            'login' => $authenticatedUser->getLogin(),
            'roles' => $authenticatedUser->getRoles(),
        ]);
    }
}
