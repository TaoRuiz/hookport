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
