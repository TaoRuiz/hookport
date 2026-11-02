<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class ProfileControllerTest extends WebTestCase
{
    private const string LOGIN = 'admin';
    private const string PASSWORD = 'correct-password';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->replaceAllUsersWithOneKnownUser();
    }

    public function testAnonymousVisitorCannotSeeAProfile(): void
    {
        $this->client->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }

    public function testSessionCookieFromLoginGivesAccessToOwnProfile(): void
    {
        $this->client->jsonRequest('POST', '/api/login', [
            'login' => self::LOGIN,
            'password' => self::PASSWORD,
        ]);
        $this->client->request('GET', '/api/me');

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['login' => self::LOGIN, 'roles' => ['ROLE_USER']],
            json_decode($this->client->getResponse()->getContent(), true),
        );
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
