<?php

namespace App\Tests\Controller;

use App\Tests\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProfileControllerTest extends WebTestCase
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
            ['login' => self::LOGIN, 'roles' => ['ROLE_USER'], 'grantedRoles' => ['ROLE_USER']],
            json_decode($this->client->getResponse()->getContent(), true),
        );
    }

    public function testProfileListsRolesInheritedFromTheRoleHierarchy(): void
    {
        $this->client->loginUser($this->createUser('configuration-admin', ['ROLE_ADMIN']));

        $this->client->request('GET', '/api/me');

        $profile = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $profile['roles']);
        self::assertContains('ROLE_CONFIGURATION_MANAGE', $profile['grantedRoles']);
        self::assertContains('ROLE_DEMO', $profile['grantedRoles']);
    }

    public function testDemoProfileDoesNotGrantConfigurationManagement(): void
    {
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/me');

        $profile = json_decode($this->client->getResponse()->getContent(), true);
        self::assertNotContains('ROLE_CONFIGURATION_MANAGE', $profile['grantedRoles']);
    }
}
