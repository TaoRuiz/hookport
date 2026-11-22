<?php

namespace App\Tests\Controller;

use App\Entity\Delivery;
use App\Entity\EventIn;
use App\Entity\Source;
use App\Enum\DeliveryStatus;
use App\Tests\CreatesSources;
use App\Tests\CreatesTargets;
use App\Tests\CreatesUsers;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EventListControllerTest extends WebTestCase
{
    use CreatesSources;
    use CreatesTargets;
    use CreatesUsers;

    private KernelBrowser $client;
    private Source $source;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->source = $this->createSource('emitter-app', 'emitter-shared-secret');
    }

    public function testLoggedInUserListsEventsNewestFirst(): void
    {
        $olderEvent = $this->storeEvent('evt_1');
        $newerEvent = $this->storeEvent('evt_2');
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/events');

        self::assertResponseIsSuccessful();
        $listedEvents = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame([(string) $newerEvent->getUuid(), (string) $olderEvent->getUuid()], array_column($listedEvents, 'uuid'));
        self::assertSame('record.created', $listedEvents[0]['type']);
        self::assertSame(['id' => $this->source->getId(), 'slug' => 'emitter-app'], $listedEvents[0]['source']);
        self::assertSame('evt_2', $listedEvents[0]['externalId']);
        self::assertArrayHasKey('receivedAt', $listedEvents[0]);
    }

    /**
     * @param list<DeliveryStatus> $deliveryStatuses
     */
    #[DataProvider('provideDeliveryStatusesAndExpectedEventState')]
    public function testEventStateIsDerivedFromItsDeliveries(array $deliveryStatuses, string $expectedEventState): void
    {
        $this->storeEventWithDeliveries($deliveryStatuses);
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/events');

        $listedEvents = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame($expectedEventState, $listedEvents[0]['state']);
    }

    public static function provideDeliveryStatusesAndExpectedEventState(): iterable
    {
        yield 'no delivery' => [[], 'unrouted'];
        yield 'all delivered' => [[DeliveryStatus::Delivered, DeliveryStatus::Delivered], 'delivered'];
        yield 'one pending' => [[DeliveryStatus::Delivered, DeliveryStatus::Pending], 'in_progress'];
        yield 'one retrying' => [[DeliveryStatus::Delivered, DeliveryStatus::Retrying], 'in_progress'];
        yield 'one failed' => [[DeliveryStatus::Delivered, DeliveryStatus::Failed], 'needs_attention'];
        yield 'failed wins over retrying' => [[DeliveryStatus::Retrying, DeliveryStatus::Failed], 'needs_attention'];
    }

    #[DataProvider('provideSearchedTextsMatchingTheSecondEvent')]
    public function testSearchKeepsOnlyEventsContainingTheSearchedText(string $searchedText): void
    {
        $this->storeEvent('evt_1', 'record.created', ['field' => 'value']);
        $matchingEvent = $this->storeEvent('evt_2', 'record.updated', ['reference' => 'REF-1234']);
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/events', ['search' => $searchedText]);

        self::assertResponseIsSuccessful();
        $listedEvents = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame([(string) $matchingEvent->getUuid()], array_column($listedEvents, 'uuid'));
    }

    public static function provideSearchedTextsMatchingTheSecondEvent(): iterable
    {
        yield 'external id' => ['evt_2'];
        yield 'type' => ['updated'];
        yield 'payload value' => ['REF-1234'];
        yield 'any case' => ['ref-1234'];
    }

    public function testSearchMatchesLikeWildcardsLiterally(): void
    {
        $this->storeEvent('evt1');
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/events', ['search' => 'evt_']);

        self::assertSame([], json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testBlankSearchListsEveryEvent(): void
    {
        $this->storeEvent('evt_1');
        $this->storeEvent('evt_2');
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/events', ['search' => '  ']);

        self::assertCount(2, json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testAnonymousVisitorCannotListEvents(): void
    {
        $this->client->request('GET', '/api/events');

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @param array<mixed> $payload
     */
    private function storeEvent(string $externalId, string $type = 'record.created', array $payload = ['field' => 'value']): EventIn
    {
        $eventIn = new EventIn($this->source, $externalId, $type, $payload);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($eventIn);
        $entityManager->flush();

        return $eventIn;
    }

    /**
     * @param list<DeliveryStatus> $deliveryStatuses
     */
    private function storeEventWithDeliveries(array $deliveryStatuses): void
    {
        $eventIn = $this->storeEvent('evt_1');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ($deliveryStatuses as $targetNumber => $deliveryStatus) {
            $target = $this->createTarget('Receiver '.$targetNumber, 'https://receiver.example.test', 'target-shared-secret');
            $entityManager->persist(new Delivery($eventIn, $target, maxAttempts: 5)->setStatus($deliveryStatus));
        }
        $entityManager->flush();
    }
}
