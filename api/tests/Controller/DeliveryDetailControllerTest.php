<?php

namespace App\Tests\Controller;

use App\Entity\Delivery;
use App\Entity\DeliveryAttempt;
use App\Entity\EventIn;
use App\Enum\DeliveryStatus;
use App\Tests\CreatesSources;
use App\Tests\CreatesTargets;
use App\Tests\CreatesUsers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DeliveryDetailControllerTest extends WebTestCase
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

    public function testLoggedInUserSeesADeliveryWithItsAttemptsInOrder(): void
    {
        $eventIn = new EventIn($this->createSource('emitter-app', 'emitter-shared-secret'), 'evt_1', 'record.created', ['field' => 'value']);
        $target = $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret');
        $delivery = new Delivery($eventIn, $target, maxAttempts: 5)->setStatus(DeliveryStatus::Retrying)->setAttemptsCount(2);
        $sentAt = new \DateTimeImmutable('2026-11-10 10:00:00');
        $this->entityManager->persist($eventIn);
        $this->entityManager->persist($delivery);
        $this->entityManager->persist(new DeliveryAttempt($delivery, 2, $sentAt->modify('+1 minute'), 5000, null, 'Timeout', null));
        $this->entityManager->persist(new DeliveryAttempt($delivery, 1, $sentAt, 120, 500, null, 'Internal error'));
        $this->entityManager->flush();
        $this->entityManager->clear();
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/deliveries/'.$delivery->getUuid());

        self::assertResponseIsSuccessful();
        $shownDelivery = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame((string) $delivery->getUuid(), $shownDelivery['uuid']);
        self::assertSame((string) $eventIn->getUuid(), $shownDelivery['eventUuid']);
        self::assertSame(['id' => $target->getId(), 'name' => 'Receiver'], $shownDelivery['target']);
        self::assertSame('retrying', $shownDelivery['status']);
        self::assertSame(2, $shownDelivery['attemptsCount']);
        self::assertSame(5, $shownDelivery['maxAttempts']);
        self::assertSame([1, 2], array_column($shownDelivery['attempts'], 'attemptNo'));
        self::assertSame(120, $shownDelivery['attempts'][0]['durationMs']);
        self::assertSame(500, $shownDelivery['attempts'][0]['responseCode']);
        self::assertSame('Internal error', $shownDelivery['attempts'][0]['responseExcerpt']);
        self::assertNull($shownDelivery['attempts'][1]['responseCode']);
        self::assertSame('Timeout', $shownDelivery['attempts'][1]['error']);
        self::assertArrayHasKey('sentAt', $shownDelivery['attempts'][0]);
    }

    public function testUnknownDeliveryIsNotFound(): void
    {
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/deliveries/01890000-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnonymousVisitorCannotSeeADelivery(): void
    {
        $this->client->request('GET', '/api/deliveries/01890000-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(401);
    }
}
