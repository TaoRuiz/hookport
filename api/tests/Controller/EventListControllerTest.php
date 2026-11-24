<?php

namespace App\Tests\Controller;

use App\Entity\Delivery;
use App\Entity\EventIn;
use App\Entity\Source;
use App\Enum\DeliveryStatus;
use App\Tests\CreatesEventTypes;
use App\Tests\CreatesSources;
use App\Tests\CreatesTargets;
use App\Tests\CreatesUsers;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EventListControllerTest extends WebTestCase
{
    use CreatesEventTypes;
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
        self::assertSame(['slug' => 'record.created', 'label' => null], $listedEvents[0]['type']);
        self::assertSame(['id' => $this->source->getId(), 'slug' => 'emitter-app', 'name' => 'Emitter-app'], $listedEvents[0]['source']);
        self::assertSame('evt_2', $listedEvents[0]['externalId']);
        self::assertArrayHasKey('receivedAt', $listedEvents[0]);
    }

    public function testListedEventCarriesTheLabelOfItsCatalogType(): void
    {
        $this->createEventType('record.created', 'Record created');
        $this->storeEvent('evt_1');
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/events');

        $listedEvents = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(['slug' => 'record.created', 'label' => 'Record created'], $listedEvents[0]['type']);
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

    public function testEachEventListsItsDeliveriesWithTargetAndStatusOrderedByTargetName(): void
    {
        $eventIn = $this->storeEvent('evt_1');
        $this->storeEvent('evt_2');
        $logisticsTarget = $this->createTarget('Logistics', 'https://logistics.example.test', 'target-shared-secret');
        $accountingTarget = $this->createTarget('Accounting', 'https://accounting.example.test', 'target-shared-secret');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $failedDelivery = new Delivery($eventIn, $logisticsTarget, maxAttempts: 5)->setStatus(DeliveryStatus::Failed);
        $deliveredDelivery = new Delivery($eventIn, $accountingTarget, maxAttempts: 5)->setStatus(DeliveryStatus::Delivered);
        $entityManager->persist($failedDelivery);
        $entityManager->persist($deliveredDelivery);
        $entityManager->flush();
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/events');

        $listedEventsByExternalId = array_column(json_decode($this->client->getResponse()->getContent(), true), null, 'externalId');
        self::assertSame([
            ['uuid' => (string) $deliveredDelivery->getUuid(), 'target' => ['id' => $accountingTarget->getId(), 'name' => 'Accounting'], 'status' => 'delivered'],
            ['uuid' => (string) $failedDelivery->getUuid(), 'target' => ['id' => $logisticsTarget->getId(), 'name' => 'Logistics'], 'status' => 'failed'],
        ], $listedEventsByExternalId['evt_1']['deliveries']);
        self::assertSame([], $listedEventsByExternalId['evt_2']['deliveries']);
    }

    public function testFiltersKeepOnlyEventsOfTheChosenTypeAndSource(): void
    {
        $otherSource = $this->createSource('other-app', 'other-shared-secret');
        $this->storeEvent('evt_1', 'record.created');
        $this->storeEvent('evt_2', 'record.updated');
        $this->storeEvent('evt_3', 'record.created', source: $otherSource);
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/events', ['type' => 'record.created', 'sourceId' => $this->source->getId()]);

        self::assertResponseIsSuccessful();
        self::assertSame(['evt_1'], array_column(json_decode($this->client->getResponse()->getContent(), true), 'externalId'));
    }

    public function testDateFilterKeepsEventsReceivedFromTheFirstDateAndBeforeTheSecond(): void
    {
        $this->receiveEventAt($this->storeEvent('evt_before'), '2026-01-09 23:59:59');
        $this->receiveEventAt($this->storeEvent('evt_first_second'), '2026-01-10 00:00:00');
        $this->receiveEventAt($this->storeEvent('evt_last_second'), '2026-01-10 23:59:59');
        $this->receiveEventAt($this->storeEvent('evt_after'), '2026-01-11 00:00:00');
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/events', ['receivedFrom' => '2026-01-10T00:00:00+00:00', 'receivedBefore' => '2026-01-11T00:00:00+00:00']);

        self::assertResponseIsSuccessful();
        self::assertSame(['evt_last_second', 'evt_first_second'], array_column(json_decode($this->client->getResponse()->getContent(), true), 'externalId'));
    }

    public function testDateFilterHonorsTheTimezoneOffsetOfTheDates(): void
    {
        $this->receiveEventAt($this->storeEvent('evt_before'), '2026-01-09 22:59:59');
        $this->receiveEventAt($this->storeEvent('evt_inside'), '2026-01-09 23:00:00');
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/events', ['receivedFrom' => '2026-01-10T00:00:00+01:00']);

        self::assertSame(['evt_inside'], array_column(json_decode($this->client->getResponse()->getContent(), true), 'externalId'));
    }

    /**
     * @param list<string> $eventStates
     * @param list<string> $expectedExternalIds
     */
    #[DataProvider('provideEventStatesAndTheEventsInThem')]
    public function testStateFilterKeepsOnlyEventsInTheChosenStates(array $eventStates, array $expectedExternalIds): void
    {
        $this->storeEventWithDeliveries([], 'evt_unrouted');
        $this->storeEventWithDeliveries([DeliveryStatus::Delivered, DeliveryStatus::Retrying], 'evt_in_progress');
        $this->storeEventWithDeliveries([DeliveryStatus::Delivered, DeliveryStatus::Delivered], 'evt_delivered');
        $this->storeEventWithDeliveries([DeliveryStatus::Pending, DeliveryStatus::Failed], 'evt_needs_attention');
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/events', ['states' => $eventStates]);

        self::assertResponseIsSuccessful();
        self::assertSame($expectedExternalIds, array_column(json_decode($this->client->getResponse()->getContent(), true), 'externalId'));
    }

    public static function provideEventStatesAndTheEventsInThem(): iterable
    {
        yield 'unrouted' => [['unrouted'], ['evt_unrouted']];
        yield 'in progress' => [['in_progress'], ['evt_in_progress']];
        yield 'delivered' => [['delivered'], ['evt_delivered']];
        yield 'needs attention' => [['needs_attention'], ['evt_needs_attention']];
        yield 'every state but delivered' => [['unrouted', 'in_progress', 'needs_attention'], ['evt_needs_attention', 'evt_in_progress', 'evt_unrouted']];
    }

    public function testUnknownStateIsRefused(): void
    {
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/events', ['states' => ['delivered', 'lost']]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testAnonymousVisitorCannotListEvents(): void
    {
        $this->client->request('GET', '/api/events');

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @param array<mixed> $payload
     */
    private function storeEvent(string $externalId, string $type = 'record.created', array $payload = ['field' => 'value'], ?Source $source = null): EventIn
    {
        $eventIn = new EventIn($source ?? $this->source, $externalId, $type, $payload);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($eventIn);
        $entityManager->flush();

        return $eventIn;
    }

    private function receiveEventAt(EventIn $eventIn, string $receivedAt): void
    {
        static::getContainer()->get(EntityManagerInterface::class)
            ->createQuery('UPDATE '.EventIn::class.' eventIn SET eventIn.receivedAt = :receivedAt WHERE eventIn.uuid = :uuid')
            ->setParameter('receivedAt', new \DateTimeImmutable($receivedAt, new \DateTimeZone('UTC')), 'datetime_immutable')
            ->setParameter('uuid', $eventIn->getUuid(), 'uuid')
            ->execute();
    }

    /**
     * @param list<DeliveryStatus> $deliveryStatuses
     */
    private function storeEventWithDeliveries(array $deliveryStatuses, string $externalId = 'evt_1'): void
    {
        $eventIn = $this->storeEvent($externalId);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        foreach ($deliveryStatuses as $targetNumber => $deliveryStatus) {
            $target = $this->createTarget('Receiver '.$targetNumber, 'https://receiver.example.test', 'target-shared-secret');
            $entityManager->persist(new Delivery($eventIn, $target, maxAttempts: 5)->setStatus($deliveryStatus));
        }
        $entityManager->flush();
    }
}
