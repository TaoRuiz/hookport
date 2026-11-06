<?php

namespace App\Tests\Controller;

use App\Tests\CreatesTargets;
use App\Tests\CreatesUsers;
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
}
