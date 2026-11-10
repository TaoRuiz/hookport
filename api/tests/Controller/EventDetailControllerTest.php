<?php

namespace App\Tests\Controller;

use App\Entity\Delivery;
use App\Entity\EventIn;
use App\Enum\DeliveryStatus;
use App\Tests\CreatesSources;
use App\Tests\CreatesTargets;
use App\Tests\CreatesUsers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EventDetailControllerTest extends WebTestCase
{
    use CreatesSources;
    use CreatesTargets;
    use CreatesUsers;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testLoggedInUserSeesAnEventWithItsDeliveries(): void
    {
        $source = $this->createSource('emitter-app', 'emitter-shared-secret');
        $eventIn = new EventIn($source, 'evt_1', 'record.created', ['field' => 'value']);
        $target = $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret');
        $delivery = new Delivery($eventIn, $target, maxAttempts: 5)->setStatus(DeliveryStatus::Failed)->setAttemptsCount(5);
        $this->entityManager->persist($eventIn);
        $this->entityManager->persist($delivery);
        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/events/'.$eventIn->getUuid());

        self::assertResponseIsSuccessful();
        $shownEvent = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame((string) $eventIn->getUuid(), $shownEvent['uuid']);
        self::assertSame('record.created', $shownEvent['type']);
        self::assertSame(['id' => $source->getId(), 'slug' => 'emitter-app'], $shownEvent['source']);
        self::assertSame('evt_1', $shownEvent['externalId']);
        self::assertArrayHasKey('receivedAt', $shownEvent);
        self::assertSame(['field' => 'value'], $shownEvent['payload']);
        self::assertSame('needs_attention', $shownEvent['state']);
        self::assertSame([[
            'uuid' => (string) $delivery->getUuid(),
            'target' => ['id' => $target->getId(), 'name' => 'Receiver'],
            'status' => 'failed',
            'attemptsCount' => 5,
            'maxAttempts' => 5,
            'nextAttemptAt' => null,
        ]], $shownEvent['deliveries']);
    }

    public function testUnknownEventIsNotFound(): void
    {
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/events/01890000-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnonymousVisitorCannotSeeAnEvent(): void
    {
        $this->client->request('GET', '/api/events/01890000-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(401);
    }
}
