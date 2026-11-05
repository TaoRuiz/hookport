<?php

namespace App\Tests\Controller;

use App\Tests\CreatesSources;
use App\Tests\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SourceManagementControllerTest extends WebTestCase
{
    use CreatesSources;
    use CreatesUsers;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testDemoSeesEverySourceSortedByNameIncludingDisabledOnes(): void
    {
        $enabledSource = $this->createSource('second-app', 'second-secret');
        $disabledSource = $this->createSource('first-app', 'first-secret', isEnabled: false);
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/sources');

        self::assertResponseIsSuccessful();
        self::assertSame(
            [
                ['id' => $disabledSource->getId(), 'slug' => 'first-app', 'name' => 'First-app', 'enabled' => false],
                ['id' => $enabledSource->getId(), 'slug' => 'second-app', 'name' => 'Second-app', 'enabled' => true],
            ],
            json_decode($this->client->getResponse()->getContent(), true),
        );
    }

    public function testSourceListNeverExposesSecrets(): void
    {
        $this->createSource('emitter-app', 'emitter-shared-secret');
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/sources');

        self::assertStringNotContainsString('emitter-shared-secret', $this->client->getResponse()->getContent());
    }

    public function testAnonymousVisitorCannotListSources(): void
    {
        $this->client->request('GET', '/api/sources');

        self::assertResponseStatusCodeSame(401);
    }
}
