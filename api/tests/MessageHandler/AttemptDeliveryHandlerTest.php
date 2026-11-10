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
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

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

    public function testRetryableFailureWithAttemptsLeftSchedulesTheNextAttempt(): void
    {
        $delivery = $this->createPendingDelivery();

        $this->attemptDeliveryWithTargetAnswering($delivery, new MockResponse('', ['http_code' => 500]));

        self::assertSame(DeliveryStatus::Retrying, $delivery->getStatus());
        self::assertSame(1, $delivery->getAttemptsCount());
        $queuedEnvelopes = $this->getAsyncTransport()->getSent();
        self::assertCount(1, $queuedEnvelopes);
        self::assertTrue($delivery->getUuid()->equals($queuedEnvelopes[0]->getMessage()->deliveryUuid));
    }

    public function testTimeoutIsRetried(): void
    {
        $delivery = $this->createPendingDelivery();

        $this->attemptDeliveryWithTargetAnswering($delivery, new MockResponse('', ['error' => 'Idle timeout reached.']));

        self::assertSame(DeliveryStatus::Retrying, $delivery->getStatus());
        self::assertCount(1, $this->getAsyncTransport()->getSent());
    }

    public static function backoffDelaysByAttemptsAlreadyMade(): iterable
    {
        yield 'after the 1st attempt' => [0, 60];
        yield 'after the 2nd attempt' => [1, 300];
        yield 'after the 3rd attempt' => [2, 1800];
        yield 'after the 4th attempt' => [3, 7200];
        yield 'after the 5th attempt' => [4, 7200];
    }

    #[DataProvider('backoffDelaysByAttemptsAlreadyMade')]
    public function testNextAttemptIsDelayedWithExponentialBackoff(int $attemptsAlreadyMade, int $expectedDelayInSeconds): void
    {
        $delivery = $this->createPendingDelivery(maxAttempts: 10, attemptsAlreadyMade: $attemptsAlreadyMade);

        $earliestExpectedNextAttemptAt = new \DateTimeImmutable("+{$expectedDelayInSeconds} seconds");
        $this->attemptDeliveryWithTargetAnswering($delivery, new MockResponse('', ['http_code' => 503]));
        $latestExpectedNextAttemptAt = new \DateTimeImmutable("+{$expectedDelayInSeconds} seconds");

        $delayStamp = $this->getAsyncTransport()->getSent()[0]->last(DelayStamp::class);
        self::assertSame($expectedDelayInSeconds * 1000, $delayStamp->getDelay());
        self::assertGreaterThanOrEqual($earliestExpectedNextAttemptAt->getTimestamp(), $delivery->getNextAttemptAt()->getTimestamp());
        self::assertLessThanOrEqual($latestExpectedNextAttemptAt->getTimestamp(), $delivery->getNextAttemptAt()->getTimestamp());
    }

    public function testRetryableFailureOnTheLastAttemptMarksTheDeliveryFailed(): void
    {
        $delivery = $this->createPendingDelivery(maxAttempts: 3, attemptsAlreadyMade: 2);

        $this->attemptDeliveryWithTargetAnswering($delivery, new MockResponse('', ['http_code' => 500]));

        self::assertSame(DeliveryStatus::Failed, $delivery->getStatus());
        self::assertSame(3, $delivery->getAttemptsCount());
        self::assertNull($delivery->getNextAttemptAt());
        self::assertCount(0, $this->getAsyncTransport()->getSent());
    }

    public function testPermanentFailureIsNotRetried(): void
    {
        $delivery = $this->createPendingDelivery();

        $this->attemptDeliveryWithTargetAnswering($delivery, new MockResponse('', ['http_code' => 400]));

        self::assertCount(0, $this->getAsyncTransport()->getSent());
    }

    public function testDeliveryToADisabledTargetFailsWithoutSendingAnything(): void
    {
        $delivery = $this->createPendingDelivery(isTargetEnabled: false);
        $httpClient = new MockHttpClient();
        static::getContainer()->set('http_client', $httpClient);

        static::getContainer()->get(AttemptDeliveryHandler::class)(new AttemptDelivery($delivery->getUuid()));

        self::assertSame(0, $httpClient->getRequestsCount());
        self::assertSame(DeliveryStatus::Failed, $delivery->getStatus());
        self::assertCount(0, $this->getAsyncTransport()->getSent());
        $recordedAttempt = static::getContainer()->get(DeliveryAttemptRepository::class)->findOneBy(['delivery' => $delivery]);
        self::assertSame('Target disabled', $recordedAttempt->getError());
        self::assertNull($recordedAttempt->getResponseCode());
    }

    private function getAsyncTransport(): InMemoryTransport
    {
        return static::getContainer()->get('messenger.transport.async');
    }

    private function attemptDeliveryWithTargetAnswering(Delivery $delivery, MockResponse $targetResponse): void
    {
        static::getContainer()->set('http_client', new MockHttpClient($targetResponse));

        static::getContainer()->get(AttemptDeliveryHandler::class)(new AttemptDelivery($delivery->getUuid()));
    }

    private function createPendingDelivery(int $maxAttempts = 5, int $attemptsAlreadyMade = 0, bool $isTargetEnabled = true): Delivery
    {
        $eventIn = new EventIn($this->createSource('emitter-app', 'emitter-shared-secret'), 'evt_1', 'record.created', ['field' => 'value']);
        $delivery = new Delivery($eventIn, $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret', $isTargetEnabled), $maxAttempts);
        $delivery->setAttemptsCount($attemptsAlreadyMade);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($eventIn);
        $entityManager->persist($delivery);
        $entityManager->flush();

        return $delivery;
    }
}
