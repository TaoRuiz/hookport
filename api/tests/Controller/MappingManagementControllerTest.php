<?php

namespace App\Tests\Controller;

use App\Repository\MappingRepository;
use App\Tests\CreatesEventTypes;
use App\Tests\CreatesMappings;
use App\Tests\CreatesSources;
use App\Tests\CreatesTargets;
use App\Tests\CreatesUsers;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MappingManagementControllerTest extends WebTestCase
{
    use CreatesEventTypes;
    use CreatesMappings;
    use CreatesSources;
    use CreatesTargets;
    use CreatesUsers;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testDemoSeesEveryMappingGroupedByTargetNameIncludingDisabledOnes(): void
    {
        $source = $this->createSource('emitter-app', 'emitter-shared-secret');
        $eventType = $this->createEventType('record.created', 'Record created');
        $secondTarget = $this->createTarget('Second target', 'https://second.example.test', 'second-secret');
        $firstTarget = $this->createTarget('First target', 'https://first.example.test', 'first-secret');
        $specificMapping = $this->createMapping($secondTarget, $source, $eventType, maxAttempts: 3);
        $wildcardMapping = $this->createMapping($firstTarget, isEnabled: false);
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/mappings');

        self::assertResponseIsSuccessful();
        self::assertSame(
            [
                [
                    'id' => $wildcardMapping->getId(),
                    'source' => null,
                    'eventType' => null,
                    'target' => ['id' => $firstTarget->getId(), 'name' => 'First target'],
                    'maxAttempts' => 5,
                    'enabled' => false,
                ],
                [
                    'id' => $specificMapping->getId(),
                    'source' => ['id' => $source->getId(), 'slug' => 'emitter-app', 'name' => 'Emitter-app'],
                    'eventType' => ['id' => $eventType->getId(), 'slug' => 'record.created', 'label' => 'Record created'],
                    'target' => ['id' => $secondTarget->getId(), 'name' => 'Second target'],
                    'maxAttempts' => 3,
                    'enabled' => true,
                ],
            ],
            json_decode($this->client->getResponse()->getContent(), true),
        );
    }

    public function testMappingListNeverExposesSecrets(): void
    {
        $source = $this->createSource('emitter-app', 'emitter-shared-secret');
        $target = $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret');
        $this->createMapping($target, $source);
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/mappings');

        $responseContent = $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('emitter-shared-secret', $responseContent);
        self::assertStringNotContainsString('target-shared-secret', $responseContent);
    }

    public function testAnonymousVisitorCannotListMappings(): void
    {
        $this->client->request('GET', '/api/mappings');

        self::assertResponseStatusCodeSame(401);
    }

    public function testAdminCreatesAnEnabledMappingForASourceAndAnEventType(): void
    {
        $source = $this->createSource('emitter-app', 'emitter-shared-secret');
        $eventType = $this->createEventType('record.created', 'Record created');
        $target = $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret');
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/mappings', [
            'sourceId' => $source->getId(),
            'eventTypeId' => $eventType->getId(),
            'targetId' => $target->getId(),
            'maxAttempts' => 3,
        ]);

        self::assertResponseStatusCodeSame(201);
        $createdMapping = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(
            [
                'id' => $createdMapping['id'],
                'source' => ['id' => $source->getId(), 'slug' => 'emitter-app', 'name' => 'Emitter-app'],
                'eventType' => ['id' => $eventType->getId(), 'slug' => 'record.created', 'label' => 'Record created'],
                'target' => ['id' => $target->getId(), 'name' => 'Receiver'],
                'maxAttempts' => 3,
                'enabled' => true,
            ],
            $createdMapping,
        );
        self::assertNotNull(static::getContainer()->get(MappingRepository::class)->find($createdMapping['id']));
    }

    public function testMappingWithoutSourceOrEventTypeMatchesAnyAndGetsDefaultMaxAttempts(): void
    {
        $target = $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret');
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/mappings', ['sourceId' => null, 'eventTypeId' => null, 'targetId' => $target->getId()]);

        self::assertResponseStatusCodeSame(201);
        $createdMapping = json_decode($this->client->getResponse()->getContent(), true);
        self::assertNull($createdMapping['source']);
        self::assertNull($createdMapping['eventType']);
        self::assertSame(5, $createdMapping['maxAttempts']);
    }

    public function testDemoCannotCreateAMapping(): void
    {
        $target = $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret');
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->jsonRequest('POST', '/api/mappings', ['targetId' => $target->getId()]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, static::getContainer()->get(MappingRepository::class)->count());
    }

    public function testIdenticalMappingIsRefused(): void
    {
        $source = $this->createSource('emitter-app', 'emitter-shared-secret');
        $target = $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret');
        $this->createMapping($target, $source);
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/mappings', ['sourceId' => $source->getId(), 'eventTypeId' => null, 'targetId' => $target->getId()]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testIdenticalWildcardMappingIsRefused(): void
    {
        $target = $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret');
        $this->createMapping($target);
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/mappings', ['targetId' => $target->getId()]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testOverlappingWildcardMappingIsAllowed(): void
    {
        $source = $this->createSource('emitter-app', 'emitter-shared-secret');
        $target = $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret');
        $this->createMapping($target);
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/mappings', ['sourceId' => $source->getId(), 'targetId' => $target->getId()]);

        self::assertResponseStatusCodeSame(201);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidMappingCreationBodies(): iterable
    {
        yield 'missing target' => [[]];
        yield 'unknown target' => [['targetId' => 999999]];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidMappingCreationBodies')]
    public function testMappingWithoutAnExistingTargetIsRejected(array $body): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/mappings', $body);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidMappingFieldsBesideTheTarget(): iterable
    {
        yield 'unknown source' => [['sourceId' => 999999]];
        yield 'unknown event type' => [['eventTypeId' => 999999]];
        yield 'zero max attempts' => [['maxAttempts' => 0]];
        yield 'too many max attempts' => [['maxAttempts' => 11]];
    }

    /**
     * @param array<string, mixed> $fieldsBesideTheTarget
     */
    #[DataProvider('invalidMappingFieldsBesideTheTarget')]
    public function testInvalidMappingFieldIsRejected(array $fieldsBesideTheTarget): void
    {
        $target = $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret');
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/mappings', ['targetId' => $target->getId(), ...$fieldsBesideTheTarget]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, static::getContainer()->get(MappingRepository::class)->count());
    }
}
