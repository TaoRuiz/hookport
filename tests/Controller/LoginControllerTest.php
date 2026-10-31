<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class LoginControllerTest extends WebTestCase
{
    private const string LOGIN = 'admin';
    private const string PASSWORD = 'correct-password';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->replaceAllUsersWithOneKnownUser();
    }

    public function testValidCredentialsReturnTheAuthenticatedUser(): void
    {
        $this->postLoginJson(self::LOGIN, self::PASSWORD);

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['login' => self::LOGIN, 'roles' => ['ROLE_USER']],
            json_decode($this->client->getResponse()->getContent(), true),
        );
    }

    public function testWrongPasswordIsRejected(): void
    {
        $this->postLoginJson(self::LOGIN, 'wrong-password');

        self::assertResponseStatusCodeSame(401);
    }

    public function testUnknownLoginIsRejected(): void
    {
        $this->postLoginJson('nobody', self::PASSWORD);

        self::assertResponseStatusCodeSame(401);
    }

    public function testRequestWithoutJsonContentTypeIsRejected(): void
    {
        $this->client->request('POST', '/api/login', [
            'login' => self::LOGIN,
            'password' => self::PASSWORD,
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    private function postLoginJson(string $login, string $password): void
    {
        $this->client->jsonRequest('POST', '/api/login', [
            'login' => $login,
            'password' => $password,
        ]);
    }

    private function replaceAllUsersWithOneKnownUser(): void
    {
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->createQuery('DELETE FROM '.User::class)->execute();

        $user = new User(self::LOGIN);
        $user->setPassword($container->get(PasswordHasherFactoryInterface::class)->getPasswordHasher($user)->hash(self::PASSWORD));
        $entityManager->persist($user);
        $entityManager->flush();
    }
}
