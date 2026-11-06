<?php

namespace App\Tests\Controller;

use App\Repository\TargetRepository;
use App\Tests\CreatesTargets;
use App\Tests\CreatesUsers;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TargetManagementControllerTest extends WebTestCase
{
    use CreatesTargets;
    use CreatesUsers;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testDemoSeesEveryTargetSortedByNameIncludingDisabledOnes(): void
    {
        $enabledTarget = $this->createTarget('Second receiver', 'https://second.example/hook', 'second-secret');
        $disabledTarget = $this->createTarget('First receiver', 'https://first.example/hook', 'first-secret', isEnabled: false);
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/targets');

        self::assertResponseIsSuccessful();
        self::assertSame(
            [
                [
                    'id' => $disabledTarget->getId(),
                    'name' => 'First receiver',
                    'protocol' => 'http',
                    'config' => ['url' => 'https://first.example/hook'],
                    'enabled' => false,
                ],
                [
                    'id' => $enabledTarget->getId(),
                    'name' => 'Second receiver',
                    'protocol' => 'http',
                    'config' => ['url' => 'https://second.example/hook'],
                    'enabled' => true,
                ],
            ],
            json_decode($this->client->getResponse()->getContent(), true),
        );
    }

    public function testTargetListNeverExposesSecrets(): void
    {
        $this->createTarget('Receiver', 'https://receiver.example/hook', 'receiver-shared-secret');
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/targets');

        self::assertStringNotContainsString('receiver-shared-secret', $this->client->getResponse()->getContent());
    }

    public function testAnonymousVisitorCannotListTargets(): void
    {
        $this->client->request('GET', '/api/targets');

        self::assertResponseStatusCodeSame(401);
    }

    public function testAdminCreatesAnEnabledHttpTargetWithAGeneratedSecret(): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/targets', ['name' => 'Receiver', 'url' => 'https://receiver.example/hook']);

        self::assertResponseStatusCodeSame(201);
        $createdTarget = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('Receiver', $createdTarget['name']);
        self::assertSame('http', $createdTarget['protocol']);
        self::assertSame(['url' => 'https://receiver.example/hook'], $createdTarget['config']);
        self::assertTrue($createdTarget['enabled']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $createdTarget['secret']);

        $storedTarget = static::getContainer()->get(TargetRepository::class)->find($createdTarget['id']);
        self::assertSame($createdTarget['secret'], $storedTarget->getSecret());
    }

    public function testTargetOnAnInternalHostWithoutTopLevelDomainIsAccepted(): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/targets', ['name' => 'Local receiver', 'url' => 'http://localhost:8000/hook']);

        self::assertResponseStatusCodeSame(201);
    }

    public function testDemoCannotCreateATarget(): void
    {
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->jsonRequest('POST', '/api/targets', ['name' => 'Receiver', 'url' => 'https://receiver.example/hook']);

        self::assertResponseStatusCodeSame(403);
        self::assertCount(0, static::getContainer()->get(TargetRepository::class)->findAll());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidTargetCreationBodies(): iterable
    {
        yield 'missing name' => [['url' => 'https://receiver.example/hook']];
        yield 'blank name' => [['name' => '', 'url' => 'https://receiver.example/hook']];
        yield 'missing url' => [['name' => 'Receiver']];
        yield 'blank url' => [['name' => 'Receiver', 'url' => '']];
        yield 'url without scheme' => [['name' => 'Receiver', 'url' => 'receiver.example/hook']];
        yield 'url with non http scheme' => [['name' => 'Receiver', 'url' => 'ftp://receiver.example/hook']];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidTargetCreationBodies')]
    public function testInvalidTargetCreationBodyIsRejected(array $body): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/targets', $body);

        self::assertResponseStatusCodeSame(422);
    }

    public function testAdminCanShowTheSecretOfATarget(): void
    {
        $target = $this->createTarget('Receiver', 'https://receiver.example/hook', 'receiver-shared-secret');
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->request('GET', '/api/targets/'.$target->getId().'/secret');

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['secret' => 'receiver-shared-secret'],
            json_decode($this->client->getResponse()->getContent(), true),
        );
    }

    public function testDemoCannotShowTheSecretOfATarget(): void
    {
        $target = $this->createTarget('Receiver', 'https://receiver.example/hook', 'receiver-shared-secret');
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/targets/'.$target->getId().'/secret');

        self::assertResponseStatusCodeSame(403);
    }

    public function testSecretOfAnUnknownTargetIsNotFound(): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->request('GET', '/api/targets/999999/secret');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAdminRenamesATarget(): void
    {
        $target = $this->createTarget('Receiver', 'https://receiver.example/hook', 'receiver-shared-secret');
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('PATCH', '/api/targets/'.$target->getId(), ['name' => 'Renamed receiver']);

        self::assertResponseIsSuccessful();
        self::assertSame(
            [
                'id' => $target->getId(),
                'name' => 'Renamed receiver',
                'protocol' => 'http',
                'config' => ['url' => 'https://receiver.example/hook'],
                'enabled' => true,
            ],
            json_decode($this->client->getResponse()->getContent(), true),
        );
    }

    public function testAdminChangesTheUrlOfATarget(): void
    {
        $target = $this->createTarget('Receiver', 'https://receiver.example/hook', 'receiver-shared-secret');
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('PATCH', '/api/targets/'.$target->getId(), ['url' => 'https://receiver.example/new-hook']);

        self::assertResponseIsSuccessful();
        $storedTarget = static::getContainer()->get(TargetRepository::class)->find($target->getId());
        self::assertSame(['url' => 'https://receiver.example/new-hook'], $storedTarget->getConfig());
    }

    public function testAdminDisablesATarget(): void
    {
        $target = $this->createTarget('Receiver', 'https://receiver.example/hook', 'receiver-shared-secret');
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('PATCH', '/api/targets/'.$target->getId(), ['enabled' => false]);

        self::assertFalse(json_decode($this->client->getResponse()->getContent(), true)['enabled']);
    }

    public function testAdminReenablesADisabledTarget(): void
    {
        $target = $this->createTarget('Receiver', 'https://receiver.example/hook', 'receiver-shared-secret', isEnabled: false);
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('PATCH', '/api/targets/'.$target->getId(), ['enabled' => true]);

        self::assertTrue(json_decode($this->client->getResponse()->getContent(), true)['enabled']);
    }

    public function testTargetFieldsNotSentAreLeftUnchanged(): void
    {
        $target = $this->createTarget('Receiver', 'https://receiver.example/hook', 'receiver-shared-secret', isEnabled: false);
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('PATCH', '/api/targets/'.$target->getId(), ['name' => 'Renamed receiver']);

        $modifiedTarget = json_decode($this->client->getResponse()->getContent(), true);
        self::assertFalse($modifiedTarget['enabled']);
        self::assertSame(['url' => 'https://receiver.example/hook'], $modifiedTarget['config']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidTargetModificationBodies(): iterable
    {
        yield 'blank name' => [['name' => '']];
        yield 'blank url' => [['url' => '']];
        yield 'url without scheme' => [['url' => 'receiver.example/hook']];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidTargetModificationBodies')]
    public function testInvalidTargetModificationBodyIsRejected(array $body): void
    {
        $target = $this->createTarget('Receiver', 'https://receiver.example/hook', 'receiver-shared-secret');
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('PATCH', '/api/targets/'.$target->getId(), $body);

        self::assertResponseStatusCodeSame(422);
    }

    public function testDemoCannotModifyATarget(): void
    {
        $target = $this->createTarget('Receiver', 'https://receiver.example/hook', 'receiver-shared-secret');
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->jsonRequest('PATCH', '/api/targets/'.$target->getId(), ['enabled' => false]);

        self::assertResponseStatusCodeSame(403);
        self::assertTrue(static::getContainer()->get(TargetRepository::class)->find($target->getId())->isEnabled());
    }
}
