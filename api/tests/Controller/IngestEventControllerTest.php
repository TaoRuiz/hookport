<?php

namespace App\Tests\Controller;

use App\Repository\EventInRepository;
use App\Tests\CreatesSources;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class IngestEventControllerTest extends WebTestCase
{
    use CreatesSources;

    private const string SOURCE_SLUG = 'emitter-app';
    private const string SOURCE_SECRET = 'emitter-shared-secret';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->createSource(self::SOURCE_SLUG, self::SOURCE_SECRET);
    }

    public function testWellFormedEventIsAccepted(): void
    {
        $this->postSignedEvent('{"type":"record.created","id":"evt_1","payload":{"field":"value"}}');

        self::assertResponseStatusCodeSame(202);
    }

    public function testAcceptedEventIsStored(): void
    {
        $this->postSignedEvent('{"type":"record.created","id":"evt_1","payload":{"field":"value"}}');

        $storedEvent = static::getContainer()->get(EventInRepository::class)->findOneBy(['externalId' => 'evt_1']);
        self::assertNotNull($storedEvent);
        self::assertSame(self::SOURCE_SLUG, $storedEvent->getSource()->getSlug());
        self::assertSame('record.created', $storedEvent->getType());
        self::assertSame(['field' => 'value'], $storedEvent->getPayload());
    }

    public function testDuplicateEventIsIgnored(): void
    {
        $this->postSignedEvent('{"type":"record.created","id":"evt_1","payload":{"field":"value"}}');
        $this->postSignedEvent('{"type":"record.updated","id":"evt_1","payload":{"field":"other"}}');

        self::assertResponseStatusCodeSame(200);
        $storedEvents = static::getContainer()->get(EventInRepository::class)->findBy(['externalId' => 'evt_1']);
        self::assertCount(1, $storedEvents);
        self::assertSame('record.created', $storedEvents[0]->getType());
    }

    public function testSameExternalIdFromAnotherSourceIsStored(): void
    {
        $this->createSource('other-emitter-app', 'other-shared-secret');
        $this->postSignedEvent('{"type":"record.created","id":"evt_1","payload":{}}');
        $this->postSignedEvent('{"type":"record.created","id":"evt_1","payload":{}}', 'other-emitter-app', 'other-shared-secret');

        self::assertResponseStatusCodeSame(202);
        self::assertCount(2, static::getContainer()->get(EventInRepository::class)->findBy(['externalId' => 'evt_1']));
    }

    public function testEventWithEmptyPayloadIsAccepted(): void
    {
        $this->postSignedEvent('{"type":"record.created","id":"evt_1","payload":{}}');

        self::assertResponseStatusCodeSame(202);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEventBodies(): iterable
    {
        yield 'missing type' => ['{"id":"evt_1","payload":{}}'];
        yield 'blank type' => ['{"type":"","id":"evt_1","payload":{}}'];
        yield 'missing id' => ['{"type":"record.created","payload":{}}'];
        yield 'blank id' => ['{"type":"record.created","id":"","payload":{}}'];
        yield 'missing payload' => ['{"type":"record.created","id":"evt_1"}'];
        yield 'payload is not an object' => ['{"type":"record.created","id":"evt_1","payload":"text"}'];
        yield 'id is not a string' => ['{"type":"record.created","id":42,"payload":{}}'];
    }

    #[DataProvider('invalidEventBodies')]
    public function testInvalidEventBodyIsRejected(string $rawBody): void
    {
        $this->postSignedEvent($rawBody);

        self::assertResponseStatusCodeSame(422);
    }

    private function postSignedEvent(
        string $rawBody,
        string $sourceSlug = self::SOURCE_SLUG,
        string $sourceSecret = self::SOURCE_SECRET,
    ): void {
        $this->client->request('POST', '/api/events', server: [
            'HTTP_X_SOURCE' => $sourceSlug,
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', $rawBody, $sourceSecret),
            'CONTENT_TYPE' => 'application/json',
        ], content: $rawBody);
    }
}
