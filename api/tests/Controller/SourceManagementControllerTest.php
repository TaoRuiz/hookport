<?php

namespace App\Tests\Controller;

use App\Repository\SourceRepository;
use App\Tests\CreatesSources;
use App\Tests\CreatesUsers;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testAdminCreatesAnEnabledSourceWithAGeneratedSecret(): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/sources', ['slug' => 'emitter-app', 'name' => 'Emitter app']);

        self::assertResponseStatusCodeSame(201);
        $createdSource = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('emitter-app', $createdSource['slug']);
        self::assertSame('Emitter app', $createdSource['name']);
        self::assertTrue($createdSource['enabled']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $createdSource['secret']);

        $storedSource = static::getContainer()->get(SourceRepository::class)->find($createdSource['id']);
        self::assertSame($createdSource['secret'], $storedSource->getSecret());
    }

    public function testEachCreatedSourceGetsADifferentSecret(): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/sources', ['slug' => 'first-app', 'name' => 'First app']);
        $firstSecret = json_decode($this->client->getResponse()->getContent(), true)['secret'];
        $this->client->jsonRequest('POST', '/api/sources', ['slug' => 'second-app', 'name' => 'Second app']);
        $secondSecret = json_decode($this->client->getResponse()->getContent(), true)['secret'];

        self::assertNotSame($firstSecret, $secondSecret);
    }

    public function testCreatedSourceCanImmediatelySendSignedEvents(): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));
        $this->client->jsonRequest('POST', '/api/sources', ['slug' => 'emitter-app', 'name' => 'Emitter app']);
        $secret = json_decode($this->client->getResponse()->getContent(), true)['secret'];

        $rawBody = '{"type":"record.created","id":"evt_1","payload":{}}';
        $this->client->request('POST', '/api/events', server: [
            'HTTP_X_SOURCE' => 'emitter-app',
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', $rawBody, $secret),
            'CONTENT_TYPE' => 'application/json',
        ], content: $rawBody);

        self::assertResponseStatusCodeSame(202);
    }

    public function testDemoCannotCreateASource(): void
    {
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->jsonRequest('POST', '/api/sources', ['slug' => 'emitter-app', 'name' => 'Emitter app']);

        self::assertResponseStatusCodeSame(403);
        self::assertNull(static::getContainer()->get(SourceRepository::class)->findOneBy(['slug' => 'emitter-app']));
    }

    public function testSlugAlreadyUsedIsRefused(): void
    {
        $this->createSource('emitter-app', 'emitter-shared-secret');
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/sources', ['slug' => 'emitter-app', 'name' => 'Another name']);

        self::assertResponseStatusCodeSame(409);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidSourceCreationBodies(): iterable
    {
        yield 'missing slug' => [['name' => 'Emitter app']];
        yield 'blank slug' => [['slug' => '', 'name' => 'Emitter app']];
        yield 'slug with uppercase' => [['slug' => 'Emitter-App', 'name' => 'Emitter app']];
        yield 'slug with spaces' => [['slug' => 'emitter app', 'name' => 'Emitter app']];
        yield 'slug with double hyphen' => [['slug' => 'emitter--app', 'name' => 'Emitter app']];
        yield 'missing name' => [['slug' => 'emitter-app']];
        yield 'blank name' => [['slug' => 'emitter-app', 'name' => '']];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidSourceCreationBodies')]
    public function testInvalidSourceCreationBodyIsRejected(array $body): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/sources', $body);

        self::assertResponseStatusCodeSame(422);
    }
}
