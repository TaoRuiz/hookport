<?php

namespace App\Tests\Controller;

use App\Repository\EventTypeRepository;
use App\Tests\CreatesEventTypes;
use App\Tests\CreatesUsers;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EventTypeManagementControllerTest extends WebTestCase
{
    use CreatesEventTypes;
    use CreatesUsers;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testDemoSeesEveryEventTypeSortedByLabelIncludingDisabledOnes(): void
    {
        $enabledEventType = $this->createEventType('record.updated', 'Record updated');
        $disabledEventType = $this->createEventType('record.created', 'Record created', isEnabled: false);
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/event-types');

        self::assertResponseIsSuccessful();
        self::assertSame(
            [
                ['id' => $disabledEventType->getId(), 'slug' => 'record.created', 'label' => 'Record created', 'enabled' => false],
                ['id' => $enabledEventType->getId(), 'slug' => 'record.updated', 'label' => 'Record updated', 'enabled' => true],
            ],
            json_decode($this->client->getResponse()->getContent(), true),
        );
    }

    public function testAnonymousVisitorCannotListEventTypes(): void
    {
        $this->client->request('GET', '/api/event-types');

        self::assertResponseStatusCodeSame(401);
    }

    public function testAdminCreatesAnEnabledEventType(): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/event-types', ['slug' => 'record.created', 'label' => 'Record created']);

        self::assertResponseStatusCodeSame(201);
        $createdEventType = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(
            ['id' => $createdEventType['id'], 'slug' => 'record.created', 'label' => 'Record created', 'enabled' => true],
            $createdEventType,
        );
        self::assertNotNull(static::getContainer()->get(EventTypeRepository::class)->find($createdEventType['id']));
    }

    public function testSlugKeepsTheNamingStyleOfTheEmitter(): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/event-types', ['slug' => 'Record_Created', 'label' => 'Record created']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('Record_Created', json_decode($this->client->getResponse()->getContent(), true)['slug']);
    }

    public function testDemoCannotCreateAnEventType(): void
    {
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->jsonRequest('POST', '/api/event-types', ['slug' => 'record.created', 'label' => 'Record created']);

        self::assertResponseStatusCodeSame(403);
        self::assertNull(static::getContainer()->get(EventTypeRepository::class)->findOneBy(['slug' => 'record.created']));
    }

    public function testEventTypeSlugAlreadyUsedIsRefused(): void
    {
        $this->createEventType('record.created', 'Record created');
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/event-types', ['slug' => 'record.created', 'label' => 'Another label']);

        self::assertResponseStatusCodeSame(409);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidEventTypeCreationBodies(): iterable
    {
        yield 'missing slug' => [['label' => 'Record created']];
        yield 'blank slug' => [['slug' => '', 'label' => 'Record created']];
        yield 'slug with spaces' => [['slug' => 'record created', 'label' => 'Record created']];
        yield 'slug too long' => [['slug' => str_repeat('a', 101), 'label' => 'Record created']];
        yield 'missing label' => [['slug' => 'record.created']];
        yield 'blank label' => [['slug' => 'record.created', 'label' => '']];
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('invalidEventTypeCreationBodies')]
    public function testInvalidEventTypeCreationBodyIsRejected(array $body): void
    {
        $this->client->loginUser($this->createUser('admin', ['ROLE_ADMIN']));

        $this->client->jsonRequest('POST', '/api/event-types', $body);

        self::assertResponseStatusCodeSame(422);
    }
}
