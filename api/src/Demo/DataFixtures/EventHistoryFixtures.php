<?php

namespace App\Demo\DataFixtures;

use App\Delivery\DeliveryResult;
use App\Entity\Delivery;
use App\Entity\DeliveryAttempt;
use App\Entity\EventIn;
use App\Entity\Source;
use App\Entity\Target;
use App\Enum\DeliveryOutcome;
use App\Enum\DeliveryStatus;
use App\Message\AttemptDelivery;
use App\Routing\EventRouter;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Ten days of received events, routed by the real EventRouter, with delivery attempts
 * simulated as the demo targets behave: billing always answers, logistics fails half
 * of the time, the team channel is unreachable.
 */
class EventHistoryFixtures extends Fixture implements DependentFixtureInterface
{
    private const int RANDOM_SEED = 42;
    private const int HISTORY_DAYS = 10;
    private const int MIN_EVENTS_PER_DAY = 20;
    private const int MAX_EVENTS_PER_DAY = 30;
    private const string UNKNOWN_EVENT_TYPE = 'payment.refunded';

    /** Same delays as AttemptDeliveryHandler, so the history looks like the worker wrote it. */
    private const array BACKOFF_DELAYS_IN_SECONDS = [60, 300, 1800, 7200];

    /** Source reference, event type and relative weight of each kind of event. */
    private const array EVENT_KINDS = [
        [SourceFixtures::ONLINE_SHOP, EventTypeFixtures::ORDER_CREATED, 30],
        [SourceFixtures::ONLINE_SHOP, EventTypeFixtures::ORDER_SHIPPED, 10],
        [SourceFixtures::ONLINE_SHOP, EventTypeFixtures::CART_ABANDONED, 5],
        [SourceFixtures::PAYMENT_PROVIDER, EventTypeFixtures::PAYMENT_SUCCEEDED, 25],
        [SourceFixtures::PAYMENT_PROVIDER, EventTypeFixtures::PAYMENT_FAILED, 6],
        [SourceFixtures::PAYMENT_PROVIDER, self::UNKNOWN_EVENT_TYPE, 4],
        [SourceFixtures::CRM, EventTypeFixtures::CUSTOMER_UPDATED, 10],
    ];

    /** @var array<string, int> */
    private array $externalIdCounterBySource = [];

    /** @var list<array{Delivery, \DateTimeImmutable}> */
    private array $deliveriesStillInFlight = [];

    public function __construct(
        private readonly EventRouter $eventRouter,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function getDependencies(): array
    {
        return [MappingFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        mt_srand(self::RANDOM_SEED);
        $now = new \DateTimeImmutable();

        foreach ($this->generateReceptionTimes($now) as $receivedAt) {
            $eventIn = $this->createRandomEvent($receivedAt);
            $manager->persist($eventIn);

            foreach ($this->eventRouter->createDeliveriesFor($eventIn) as $delivery) {
                $this->simulateAttempts($manager, $delivery, $receivedAt, $now);
            }
        }
        $manager->flush();

        $this->queueDeliveriesStillInFlight($now);
    }

    /**
     * @return list<\DateTimeImmutable>
     */
    private function generateReceptionTimes(\DateTimeImmutable $now): array
    {
        $receptionTimes = [];
        for ($daysAgo = self::HISTORY_DAYS - 1; $daysAgo >= 0; --$daysAgo) {
            $dayStart = $now->setTime(0, 0)->modify("-{$daysAgo} days");
            $eventsCount = mt_rand(self::MIN_EVENTS_PER_DAY, self::MAX_EVENTS_PER_DAY);
            for ($eventIndex = 0; $eventIndex < $eventsCount; ++$eventIndex) {
                $receivedAt = $dayStart->modify('+'.mt_rand(0, 86399).' seconds');
                if ($receivedAt < $now) {
                    $receptionTimes[] = $receivedAt;
                }
            }
        }
        sort($receptionTimes);

        return $receptionTimes;
    }

    private function createRandomEvent(\DateTimeImmutable $receivedAt): EventIn
    {
        [$sourceReference, $eventType] = $this->pickEventKind();
        $externalId = \sprintf('%s-%05d', $sourceReference, $this->nextExternalIdCounter($sourceReference));

        $eventIn = new EventIn(
            $this->getReference($sourceReference, Source::class),
            $externalId,
            $eventType,
            $this->buildPayload($eventType),
        );
        // receivedAt is frozen to "now" by the constructor; the history needs past dates.
        new \ReflectionProperty(EventIn::class, 'receivedAt')->setValue($eventIn, $receivedAt);

        return $eventIn;
    }

    /**
     * @return array{string, string}
     */
    private function pickEventKind(): array
    {
        $totalWeight = array_sum(array_column(self::EVENT_KINDS, 2));
        $pickedWeight = mt_rand(1, $totalWeight);
        foreach (self::EVENT_KINDS as [$sourceReference, $eventType, $weight]) {
            $pickedWeight -= $weight;
            if ($pickedWeight <= 0) {
                return [$sourceReference, $eventType];
            }
        }

        throw new \LogicException('The picked weight always falls within the total weight.');
    }

    private function nextExternalIdCounter(string $sourceReference): int
    {
        $this->externalIdCounterBySource[$sourceReference] = ($this->externalIdCounterBySource[$sourceReference] ?? 10000) + 1;

        return $this->externalIdCounterBySource[$sourceReference];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(string $eventType): array
    {
        $orderId = \sprintf('ORD-%06d', mt_rand(1, 999999));
        $customerId = \sprintf('CUS-%05d', mt_rand(1, 99999));
        $amountInCents = mt_rand(990, 45000);

        return match ($eventType) {
            EventTypeFixtures::ORDER_CREATED => ['orderId' => $orderId, 'customerId' => $customerId, 'itemsCount' => mt_rand(1, 6), 'totalAmount' => $amountInCents, 'currency' => 'EUR'],
            EventTypeFixtures::ORDER_SHIPPED => ['orderId' => $orderId, 'carrier' => $this->pickOne(['Colissimo', 'DHL', 'UPS']), 'trackingNumber' => \sprintf('TRK%010d', mt_rand(1, 999999999))],
            EventTypeFixtures::CART_ABANDONED => ['cartId' => \sprintf('CART-%06d', mt_rand(1, 999999)), 'customerId' => $customerId, 'itemsCount' => mt_rand(1, 4), 'totalAmount' => $amountInCents, 'currency' => 'EUR'],
            EventTypeFixtures::PAYMENT_SUCCEEDED => ['paymentId' => \sprintf('pay_%08d', mt_rand(1, 99999999)), 'orderId' => $orderId, 'amount' => $amountInCents, 'currency' => 'EUR'],
            EventTypeFixtures::PAYMENT_FAILED => ['paymentId' => \sprintf('pay_%08d', mt_rand(1, 99999999)), 'orderId' => $orderId, 'amount' => $amountInCents, 'currency' => 'EUR', 'failureCode' => $this->pickOne(['card_declined', 'insufficient_funds', 'expired_card'])],
            self::UNKNOWN_EVENT_TYPE => ['paymentId' => \sprintf('pay_%08d', mt_rand(1, 99999999)), 'amount' => $amountInCents, 'currency' => 'EUR'],
            EventTypeFixtures::CUSTOMER_UPDATED => ['customerId' => $customerId, 'changedFields' => [$this->pickOne(['email', 'phone', 'shippingAddress', 'marketingConsent'])]],
        };
    }

    /**
     * @param list<string> $values
     */
    private function pickOne(array $values): string
    {
        return $values[mt_rand(0, \count($values) - 1)];
    }

    /**
     * Replays what the worker would have done up to now. An attempt due in the future
     * leaves the delivery retrying, to be picked up by the real worker.
     */
    private function simulateAttempts(ObjectManager $manager, Delivery $delivery, \DateTimeImmutable $receivedAt, \DateTimeImmutable $now): void
    {
        $sentAt = $receivedAt->modify('+'.mt_rand(1, 3).' seconds');
        for ($attemptNumber = 1;; ++$attemptNumber) {
            if ($sentAt > $now) {
                $delivery->setStatus(1 === $attemptNumber ? DeliveryStatus::Pending : DeliveryStatus::Retrying)->setNextAttemptAt($sentAt);
                $this->deliveriesStillInFlight[] = [$delivery, $sentAt];

                return;
            }

            $deliveryResult = $this->simulateDeliveryResult($delivery->getTarget());
            $manager->persist(new DeliveryAttempt(
                $delivery,
                $attemptNumber,
                $sentAt,
                $this->simulateDurationInMilliseconds($deliveryResult),
                $deliveryResult->responseCode,
                $deliveryResult->failureReason,
                $deliveryResult->responseExcerpt,
            ));
            $delivery->setAttemptsCount($attemptNumber);

            $isRetryable = DeliveryOutcome::RetryableFailure === $deliveryResult->outcome;
            if (!$isRetryable || $attemptNumber >= $delivery->getMaxAttempts()) {
                $delivery
                    ->setStatus(DeliveryOutcome::Delivered === $deliveryResult->outcome ? DeliveryStatus::Delivered : DeliveryStatus::Failed)
                    ->setNextAttemptAt(null);

                return;
            }

            $backoffDelayInSeconds = self::BACKOFF_DELAYS_IN_SECONDS[min($attemptNumber, \count(self::BACKOFF_DELAYS_IN_SECONDS)) - 1];
            $sentAt = $sentAt->modify("+{$backoffDelayInSeconds} seconds");
        }
    }

    private function simulateDeliveryResult(Target $target): DeliveryResult
    {
        $url = $target->getConfig()['url'];

        return match ($target) {
            $this->getReference(TargetFixtures::BILLING, Target::class) => new DeliveryResult(DeliveryOutcome::Delivered, 200, '{"status":"accepted"}'),
            $this->getReference(TargetFixtures::LOGISTICS, Target::class) => match (mt_rand(1, 4)) {
                1, 2 => new DeliveryResult(DeliveryOutcome::Delivered, 202, '{"queued":true}'),
                3 => new DeliveryResult(DeliveryOutcome::RetryableFailure, 500, '{"error":"Internal Server Error"}'),
                4 => new DeliveryResult(DeliveryOutcome::RetryableFailure, failureReason: \sprintf('Max duration was reached for "%s".', $url)),
            },
            $this->getReference(TargetFixtures::TEAM_CHANNEL, Target::class) => new DeliveryResult(DeliveryOutcome::RetryableFailure, failureReason: \sprintf('Couldn\'t connect to server for "%s".', $url)),
            default => new DeliveryResult(DeliveryOutcome::PermanentFailure, failureReason: 'Target disabled'),
        };
    }

    private function simulateDurationInMilliseconds(DeliveryResult $deliveryResult): int
    {
        if (null !== $deliveryResult->responseCode) {
            return mt_rand(60, 400);
        }

        return str_starts_with((string) $deliveryResult->failureReason, 'Max duration') ? 5000 : mt_rand(1, 30);
    }

    private function queueDeliveriesStillInFlight(\DateTimeImmutable $now): void
    {
        foreach ($this->deliveriesStillInFlight as [$delivery, $nextAttemptAt]) {
            $delayInMilliseconds = ($nextAttemptAt->getTimestamp() - $now->getTimestamp()) * 1000;
            $this->messageBus->dispatch(new AttemptDelivery($delivery->getUuid()), [new DelayStamp($delayInMilliseconds)]);
        }
    }
}
