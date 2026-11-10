<?php

namespace App\Tests\Controller;

use App\Entity\Delivery;
use App\Entity\EventIn;
use App\Entity\Target;
use App\Enum\DeliveryStatus;
use App\Message\AttemptDelivery;
use App\MessageHandler\AttemptDeliveryHandler;
use App\Repository\DeliveryRepository;
use App\Tests\CreatesSources;
use App\Tests\CreatesTargets;
use App\Tests\CreatesUsers;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class DeliveryReplayControllerTest extends WebTestCase
{
    use CreatesSources;
    use CreatesTargets;
    use CreatesUsers;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testReplayOfAFailedDeliveryResetsItToPendingAndQueuesAnAttempt(): void
    {
        $failedDelivery = $this->storeDelivery(DeliveryStatus::Failed);
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('POST', '/api/deliveries/'.$failedDelivery->getUuid().'/replay');

        self::assertResponseStatusCodeSame(202);
        $replayedDelivery = static::getContainer()->get(DeliveryRepository::class)->find($failedDelivery->getUuid());
        self::assertSame(DeliveryStatus::Pending, $replayedDelivery->getStatus());
        $queuedEnvelopes = $this->getAsyncTransport()->getSent();
        self::assertCount(1, $queuedEnvelopes);
        self::assertTrue($failedDelivery->getUuid()->equals($queuedEnvelopes[0]->getMessage()->deliveryUuid));
    }

    public function testDeliveryToADisabledTargetIsDeliveredWhenReplayedAfterReEnablingTheTarget(): void
    {
        $receiver = $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret', isEnabled: false);
        $delivery = $this->storeDelivery(DeliveryStatus::Pending, $receiver);
        static::getContainer()->set('http_client', new MockHttpClient(new MockResponse('', ['http_code' => 200])));
        $this->attemptDelivery($delivery);
        self::assertSame(DeliveryStatus::Failed, $delivery->getStatus());
        $receiver->setEnabled(true);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('POST', '/api/deliveries/'.$delivery->getUuid().'/replay');
        $this->attemptDelivery($delivery);

        self::assertSame(DeliveryStatus::Delivered, $delivery->getStatus());
        self::assertSame(2, $delivery->getAttemptsCount());
    }

    public function testReplayOfANotFailedDeliveryIsRefused(): void
    {
        $pendingDelivery = $this->storeDelivery(DeliveryStatus::Pending);
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('POST', '/api/deliveries/'.$pendingDelivery->getUuid().'/replay');

        self::assertResponseStatusCodeSame(409);
        self::assertCount(0, $this->getAsyncTransport()->getSent());
    }

    public function testReplayOfAnUnknownDeliveryIsNotFound(): void
    {
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('POST', '/api/deliveries/01890000-0000-7000-8000-000000000000/replay');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAnonymousVisitorCannotReplayADelivery(): void
    {
        $failedDelivery = $this->storeDelivery(DeliveryStatus::Failed);

        $this->client->request('POST', '/api/deliveries/'.$failedDelivery->getUuid().'/replay');

        self::assertResponseStatusCodeSame(401);
    }

    private function getAsyncTransport(): InMemoryTransport
    {
        return static::getContainer()->get('messenger.transport.async');
    }

    private function attemptDelivery(Delivery $delivery): void
    {
        static::getContainer()->get(AttemptDeliveryHandler::class)(new AttemptDelivery($delivery->getUuid()));
    }

    private function storeDelivery(DeliveryStatus $status, ?Target $target = null): Delivery
    {
        $eventIn = new EventIn($this->createSource('emitter-app', 'emitter-shared-secret'), 'evt_1', 'record.created', ['field' => 'value']);
        $target ??= $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret');
        $delivery = new Delivery($eventIn, $target, maxAttempts: 5)->setStatus($status);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($eventIn);
        $entityManager->persist($delivery);
        $entityManager->flush();

        return $delivery;
    }
}
