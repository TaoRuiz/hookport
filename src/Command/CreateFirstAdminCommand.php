<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:create-first-admin', description: 'Creates the first administrator account.')]
final readonly class CreateFirstAdminCommand
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument] string $login): int
    {
        if ($this->userRepository->hasAnyUserWithRole('ROLE_ADMIN')) {
            $io->note('An administrator already exists: nothing was created.');

            return Command::SUCCESS;
        }

        $passwordQuestion = (new Question('Password (at least 12 characters)'))
            ->setHidden(true)
            ->setMaxAttempts(3)
            ->setValidator($this->validatePassword(...));
        $plainPassword = $io->askQuestion($passwordQuestion);

        $admin = new User($login);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, $plainPassword));
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success(sprintf('Administrator "%s" created.', $login));

        return Command::SUCCESS;
    }

    private function validatePassword(?string $plainPassword): string
    {
        $violations = $this->validator->validate($plainPassword, [new Assert\NotBlank(), new Assert\Length(min: 12)]);
        if (\count($violations) > 0) {
            throw new \InvalidArgumentException((string) $violations->get(0)->getMessage());
        }

        return $plainPassword;
    }
}
