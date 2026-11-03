<?php

namespace App\Tests\Security;

use App\Tests\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LogoutTest extends WebTestCase
{
    use CreatesUsers;

    private const string LOGIN = 'admin';
    private const string PASSWORD = 'correct-password';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->createUser(self::LOGIN, plainPassword: self::PASSWORD);
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
}
