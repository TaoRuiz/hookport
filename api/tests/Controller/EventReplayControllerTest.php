<?php

namespace App\Tests\Controller;

use App\Entity\Delivery;
use App\Entity\EventIn;
use App\Entity\Source;
use App\Repository\DeliveryRepository;
use App\Tests\CreatesMappings;
use App\Tests\CreatesSources;
use App\Tests\CreatesTargets;
use App\Tests\CreatesUsers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class EventReplayControllerTest extends WebTestCase
{
    use CreatesMappings;
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

    public function testReplayOfAnUnroutedEventCreatesDeliveriesOnceAMappingExists(): void
    {
        $unroutedEvent = $this->storeEventWithoutDelivery();
        $receiver = $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret');
        $this->createMapping($receiver, $this->source, maxAttempts: 3);
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('POST', '/api/events/'.$unroutedEvent->getUuid().'/replay');

        self::assertResponseIsSuccessful();
        $createdDeliveries = json_decode($this->client->getResponse()->getContent(), true);
        self::assertCount(1, $createdDeliveries);
        self::assertSame(['id' => $receiver->getId(), 'name' => 'Receiver'], $createdDeliveries[0]['target']);
        self::assertSame('pending', $createdDeliveries[0]['status']);
        self::assertSame(3, $createdDeliveries[0]['maxAttempts']);
        self::assertSame(1, static::getContainer()->get(DeliveryRepository::class)->count(['eventIn' => $unroutedEvent]));
    }

    public function testReplayQueuesOneDeliveryAttemptPerCreatedDelivery(): void
    {
        $unroutedEvent = $this->storeEventWithoutDelivery();
        $this->createMapping($this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret'));
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('POST', '/api/events/'.$unroutedEvent->getUuid().'/replay');

        $createdDelivery = static::getContainer()->get(DeliveryRepository::class)->findOneBy(['eventIn' => $unroutedEvent]);
        $queuedEnvelopes = $this->getAsyncTransport()->getSent();
        self::assertCount(1, $queuedEnvelopes);
        self::assertTrue($createdDelivery->getUuid()->equals($queuedEnvelopes[0]->getMessage()->deliveryUuid));
    }

    public function testReplayWithStillNoMatchingMappingCreatesNothing(): void
    {
        $unroutedEvent = $this->storeEventWithoutDelivery();
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('POST', '/api/events/'.$unroutedEvent->getUuid().'/replay');

        self::assertResponseIsSuccessful();
        self::assertSame([], json_decode($this->client->getResponse()->getContent(), true));
    }

    public function testReplayOfAnAlreadyRoutedEventIsRefused(): void
    {
        $routedEvent = $this->storeEventWithoutDelivery();
        $receiver = $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret');
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new Delivery($routedEvent, $receiver, maxAttempts: 5));
        $entityManager->flush();
        $entityManager->clear();
        $this->createMapping($this->createTarget('Other receiver', 'https://other.example.test', 'other-secret'));
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('POST', '/api/events/'.$routedEvent->getUuid().'/replay');

        self::assertResponseStatusCodeSame(409);
        self::assertSame(1, static::getContainer()->get(DeliveryRepository::class)->count());
    }

    public function testReplayOfAnUnknownEventIsNotFound(): void
    {
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('POST', '/api/events/01890000-0000-7000-8000-000000000000/replay');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnonymousVisitorCannotReplayAnEvent(): void
    {
        $unroutedEvent = $this->storeEventWithoutDelivery();

        $this->client->request('POST', '/api/events/'.$unroutedEvent->getUuid().'/replay');

        self::assertResponseStatusCodeSame(401);
    }

    private function getAsyncTransport(): InMemoryTransport
    {
        return static::getContainer()->get('messenger.transport.async');
    }

    private function storeEventWithoutDelivery(): EventIn
    {
        $eventIn = new EventIn($this->source, 'evt_1', 'record.created', ['field' => 'value']);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($eventIn);
        $entityManager->flush();

        return $eventIn;
    }
}
