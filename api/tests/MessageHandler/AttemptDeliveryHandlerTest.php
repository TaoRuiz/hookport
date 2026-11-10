<?php

namespace App\Tests\MessageHandler;

use App\Entity\Delivery;
use App\Entity\EventIn;
use App\Enum\DeliveryStatus;
use App\Message\AttemptDelivery;
use App\MessageHandler\AttemptDeliveryHandler;
use App\Repository\DeliveryAttemptRepository;
use App\Tests\CreatesSources;
use App\Tests\CreatesTargets;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AttemptDeliveryHandlerTest extends KernelTestCase
{
    use CreatesSources;
    use CreatesTargets;

    public function testSuccessfulAttemptMarksTheDeliveryDelivered(): void
    {
        $delivery = $this->createPendingDelivery();

        $this->attemptDeliveryWithTargetAnswering($delivery, new MockResponse('received', ['http_code' => 200]));

        self::assertSame(DeliveryStatus::Delivered, $delivery->getStatus());
        self::assertSame(1, $delivery->getAttemptsCount());
    }

    public function testAttemptIsRecordedWithTheTargetResponse(): void
    {
        $delivery = $this->createPendingDelivery();

        $this->attemptDeliveryWithTargetAnswering($delivery, new MockResponse('received', ['http_code' => 200]));

        $recordedAttempts = static::getContainer()->get(DeliveryAttemptRepository::class)->findBy(['delivery' => $delivery]);
        self::assertCount(1, $recordedAttempts);
        self::assertSame(1, $recordedAttempts[0]->getAttemptNo());
        self::assertSame(200, $recordedAttempts[0]->getResponseCode());
        self::assertSame('received', $recordedAttempts[0]->getResponseExcerpt());
        self::assertNull($recordedAttempts[0]->getError());
        self::assertGreaterThanOrEqual(0, $recordedAttempts[0]->getDurationMs());
    }

    public function testPermanentFailureMarksTheDeliveryFailed(): void
    {
        $delivery = $this->createPendingDelivery();

        $this->attemptDeliveryWithTargetAnswering($delivery, new MockResponse('', ['http_code' => 400]));

        self::assertSame(DeliveryStatus::Failed, $delivery->getStatus());
        self::assertSame(1, $delivery->getAttemptsCount());
    }

    private function attemptDeliveryWithTargetAnswering(Delivery $delivery, MockResponse $targetResponse): void
    {
        static::getContainer()->set('http_client', new MockHttpClient($targetResponse));

        static::getContainer()->get(AttemptDeliveryHandler::class)(new AttemptDelivery($delivery->getUuid()));
    }

    private function createPendingDelivery(): Delivery
    {
        $eventIn = new EventIn($this->createSource('emitter-app', 'emitter-shared-secret'), 'evt_1', 'record.created', ['field' => 'value']);
        $delivery = new Delivery($eventIn, $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret'), 5);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($eventIn);
        $entityManager->persist($delivery);
        $entityManager->flush();

        return $delivery;
    }
}
