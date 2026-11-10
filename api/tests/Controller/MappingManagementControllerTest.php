<?php

namespace App\Tests\Controller;

use App\Tests\CreatesEventTypes;
use App\Tests\CreatesMappings;
use App\Tests\CreatesSources;
use App\Tests\CreatesTargets;
use App\Tests\CreatesUsers;
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
}
