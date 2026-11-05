<?php

namespace App\Tests;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

trait CreatesUsers
{
    /**
     * @param list<string> $roles
     */
    private function createUser(string $login, array $roles = [], string $plainPassword = 'unused-test-password'): User
    {
        $container = static::getContainer();
        $user = new User($login);
        $user->setRoles($roles);
        $user->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($user, $plainPassword));

        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
