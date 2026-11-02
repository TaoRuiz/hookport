<?php

namespace App\Tests\Command;

use App\Repository\UserRepository;
use App\Tests\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateFirstAdminCommandTest extends KernelTestCase
{
    use CreatesUsers;

    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->commandTester = new CommandTester((new Application(self::bootKernel()))->find('app:create-first-admin'));
    }

    public function testCreatesAnAdministratorWithTheTypedPassword(): void
    {
        $this->commandTester->setInputs(['a-long-enough-password']);

        $this->commandTester->execute(['login' => 'admin']);

        $this->commandTester->assertCommandIsSuccessful();
        $admin = static::getContainer()->get(UserRepository::class)->findOneBy(['login' => 'admin']);
        self::assertNotNull($admin);
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $admin->getRoles());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($admin, 'a-long-enough-password'));
    }

    public function testDoesNothingWhenAnAdministratorAlreadyExists(): void
    {
        $this->createUser('existing-admin', ['ROLE_ADMIN']);

        $this->commandTester->execute(['login' => 'second-admin']);

        $this->commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('An administrator already exists', $this->commandTester->getDisplay());
        self::assertNull(static::getContainer()->get(UserRepository::class)->findOneBy(['login' => 'second-admin']));
    }

    public function testStillCreatesAnAdministratorWhenOnlyDemoUsersExist(): void
    {
        $this->createUser('demo', ['ROLE_DEMO']);
        $this->commandTester->setInputs(['a-long-enough-password']);

        $this->commandTester->execute(['login' => 'admin']);

        self::assertNotNull(static::getContainer()->get(UserRepository::class)->findOneBy(['login' => 'admin']));
    }
}
