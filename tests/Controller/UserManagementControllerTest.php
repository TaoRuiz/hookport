<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserManagementControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager->createQuery('DELETE FROM '.User::class)->execute();
    }

    public function testAdminSeesEveryUserSortedByLogin(): void
    {
        $admin = $this->createUser('admin', ['ROLE_ADMIN']);
        $demo = $this->createUser('demo', ['ROLE_DEMO']);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/api/users');

        self::assertResponseIsSuccessful();
        self::assertSame(
            [
                ['id' => $admin->getId(), 'login' => 'admin', 'roles' => ['ROLE_ADMIN', 'ROLE_USER']],
                ['id' => $demo->getId(), 'login' => 'demo', 'roles' => ['ROLE_DEMO', 'ROLE_USER']],
            ],
            json_decode($this->client->getResponse()->getContent(), true),
        );
    }

    public function testDemoCannotListUsers(): void
    {
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/users');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAnonymousVisitorCannotListUsers(): void
    {
        $this->client->request('GET', '/api/users');

        self::assertResponseStatusCodeSame(401);
    }

    public function testAdminCreatesAUserWhoCanThenLogIn(): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/users', [
            'login' => 'new-demo',
            'password' => 'a-long-enough-password',
            'role' => 'ROLE_DEMO',
        ]);

        self::assertResponseStatusCodeSame(201);
        $createdUser = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('new-demo', $createdUser['login']);
        self::assertSame(['ROLE_DEMO', 'ROLE_USER'], $createdUser['roles']);

        $this->client->request('POST', '/api/logout');
        $this->client->jsonRequest('POST', '/api/login', [
            'login' => 'new-demo',
            'password' => 'a-long-enough-password',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testCreationWithAnAlreadyUsedLoginIsRefused(): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/users', [
            'login' => 'admin',
            'password' => 'a-long-enough-password',
            'role' => 'ROLE_DEMO',
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testCreationWithInvalidFieldsIsRefused(): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/users', [
            'login' => '',
            'password' => 'too-short',
            'role' => 'ROLE_UNKNOWN',
        ]);

        self::assertResponseStatusCodeSame(422);
        $violatedFields = array_column(
            json_decode($this->client->getResponse()->getContent(), true)['violations'],
            'propertyPath',
        );
        self::assertSame(['login', 'password', 'role'], $violatedFields);
    }

    public function testDemoCannotCreateUsers(): void
    {
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->jsonRequest('POST', '/api/users', [
            'login' => 'intruder',
            'password' => 'a-long-enough-password',
            'role' => 'ROLE_ADMIN',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminChangesTheRoleOfAUser(): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));
        $demo = $this->createUser('demo', ['ROLE_DEMO']);

        $this->client->jsonRequest('PATCH', '/api/users/'.$demo->getId(), ['role' => 'ROLE_ADMIN']);

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['ROLE_ADMIN', 'ROLE_USER'],
            json_decode($this->client->getResponse()->getContent(), true)['roles'],
        );
    }

    public function testAdminChangesThePasswordOfAUserWhoCanThenLogInWithIt(): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));
        $demo = $this->createUser('demo', ['ROLE_DEMO']);

        $this->client->jsonRequest('PATCH', '/api/users/'.$demo->getId(), ['password' => 'a-brand-new-password']);

        self::assertResponseIsSuccessful();

        $this->client->request('POST', '/api/logout');
        $this->client->jsonRequest('POST', '/api/login', [
            'login' => 'demo',
            'password' => 'a-brand-new-password',
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testAdminCannotChangeOwnRole(): void
    {
        $admin = $this->createUser('admin', ['ROLE_ADMIN']);
        $this->client->loginUser($admin);

        $this->client->jsonRequest('PATCH', '/api/users/'.$admin->getId(), ['role' => 'ROLE_DEMO']);

        self::assertResponseStatusCodeSame(403);
        $this->entityManager->refresh($admin);
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $admin->getRoles());
    }

    public function testModificationWithInvalidFieldsIsRefused(): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));
        $demo = $this->createUser('demo', ['ROLE_DEMO']);

        $this->client->jsonRequest('PATCH', '/api/users/'.$demo->getId(), [
            'password' => 'too-short',
            'role' => 'ROLE_UNKNOWN',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testModificationOfAnUnknownUserIsNotFound(): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('PATCH', '/api/users/999999', ['role' => 'ROLE_DEMO']);

        self::assertResponseStatusCodeSame(404);
    }

    public function testDemoCannotModifyUsers(): void
    {
        $demo = $this->createUser('demo', ['ROLE_DEMO']);
        $this->client->loginUser($demo);

        $this->client->jsonRequest('PATCH', '/api/users/'.$demo->getId(), ['role' => 'ROLE_ADMIN']);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $login, array $roles): User
    {
        $user = new User($login);
        $user->setRoles($roles);
        $user->setPassword('not-used-by-these-tests');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
