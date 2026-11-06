<?php

namespace App\Tests\Controller;

use App\Tests\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginControllerTest extends WebTestCase
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

    public function testValidCredentialsReturnTheAuthenticatedUser(): void
    {
        $this->postLoginJson(self::LOGIN, self::PASSWORD);

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['login' => self::LOGIN, 'roles' => ['ROLE_USER'], 'grantedRoles' => ['ROLE_USER']],
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
}
