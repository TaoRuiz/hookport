<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-first-admin', description: 'Creates the first administrator account.')]
final readonly class CreateFirstAdminCommand
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument] string $login): int
    {
        if ($this->userRepository->hasAnyUserWithRole('ROLE_ADMIN')) {
            $io->note('An administrator already exists: nothing was created.');

            return Command::SUCCESS;
        }

        $plainPassword = $io->askHidden('Password');

        $admin = new User($login);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $plainPassword));
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success(sprintf('Administrator "%s" created.', $login));

        return Command::SUCCESS;
    }
}
