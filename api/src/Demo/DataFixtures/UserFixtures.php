<?php

namespace App\Demo\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $manager->persist($this->createUser('admin', 'admin-password', 'ROLE_ADMIN'));
        $manager->persist($this->createUser('demo', 'demo-password', 'ROLE_DEMO'));

        $manager->flush();
    }

    private function createUser(string $login, string $plainPassword, string $role): User
    {
        $user = new User($login);
        $user->setRoles([$role]);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        return $user;
    }
}
