<?php

namespace App\Tests\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class LogoutTest extends WebTestCase
{
    private const string LOGIN = 'admin';
    private const string PASSWORD = 'correct-password';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->replaceAllUsersWithOneKnownUser();
    }

    public function testLogoutAnswersNoContent(): void
    {
        $this->logIn();
        $this->client->request('POST', '/api/logout');

        self::assertResponseStatusCodeSame(204);
    }

    public function testProfileIsNoLongerAccessibleAfterLogout(): void
    {
        $this->logIn();
        $this->client->request('POST', '/api/logout');
        $this->client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }

    public function testLogoutWithGetIsRefusedAndKeepsTheSession(): void
    {
        $this->logIn();
        $this->client->request('GET', '/api/logout');

        self::assertResponseStatusCodeSame(405);

        $this->client->request('GET', '/api/me');

        self::assertResponseIsSuccessful();
    }

    private function logIn(): void
    {
        $this->client->jsonRequest('POST', '/api/login', [
            'login' => self::LOGIN,
            'password' => self::PASSWORD,
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
