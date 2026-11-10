<?php

namespace App\MessageHandler;

use App\Delivery\DeliveryResult;
use App\Delivery\DeliveryTransport;
use App\Entity\Delivery;
use App\Entity\DeliveryAttempt;
use App\Enum\DeliveryOutcome;
use App\Enum\DeliveryStatus;
use App\Message\AttemptDelivery;
use App\Repository\DeliveryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[AsMessageHandler]
final readonly class AttemptDeliveryHandler
{
    /** Delay before the next attempt, by attempt number; the last one repeats. */
    private const array BACKOFF_DELAYS_IN_SECONDS = [60, 300, 1800, 7200];
    private const string TARGET_DISABLED_REASON = 'Target disabled';

    public function __construct(
        private DeliveryRepository $deliveryRepository,
        private DeliveryTransport $deliveryTransport,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(AttemptDelivery $attemptDelivery): void
    {
        $delivery = $this->deliveryRepository->find($attemptDelivery->deliveryUuid);
        if (null === $delivery) {
            return;
        }

        $attemptNumber = $delivery->getAttemptsCount() + 1;
        $sentAt = new \DateTimeImmutable();
        $startedAtInNanoseconds = hrtime(true);
        $deliveryResult = $delivery->getTarget()->isEnabled()
            ? $this->deliveryTransport->deliver($delivery->getEventIn(), $delivery->getTarget(), $attemptNumber)
            : new DeliveryResult(DeliveryOutcome::PermanentFailure, failureReason: self::TARGET_DISABLED_REASON);
        $durationInMilliseconds = intdiv(hrtime(true) - $startedAtInNanoseconds, 1_000_000);

        $this->entityManager->persist(new DeliveryAttempt(
            $delivery,
            $attemptNumber,
            $sentAt,
            $durationInMilliseconds,
            $deliveryResult->responseCode,
            $deliveryResult->failureReason,
            $deliveryResult->responseExcerpt,
        ));
        $delivery->setAttemptsCount($attemptNumber);

        $hasAttemptsLeft = $attemptNumber < $delivery->getMaxAttempts();
        if (DeliveryOutcome::RetryableFailure === $deliveryResult->outcome && $hasAttemptsLeft) {
            $this->scheduleNextAttempt($delivery, $attemptNumber);

            return;
        }

        $delivery
            ->setStatus(DeliveryOutcome::Delivered === $deliveryResult->outcome ? DeliveryStatus::Delivered : DeliveryStatus::Failed)
            ->setNextAttemptAt(null);
        $this->entityManager->flush();
    }

    private function scheduleNextAttempt(Delivery $delivery, int $attemptNumber): void
    {
        $delayInSeconds = self::BACKOFF_DELAYS_IN_SECONDS[min($attemptNumber, \count(self::BACKOFF_DELAYS_IN_SECONDS)) - 1];

        $delivery
            ->setStatus(DeliveryStatus::Retrying)
            ->setNextAttemptAt(new \DateTimeImmutable("+{$delayInSeconds} seconds"));
        $this->entityManager->flush();

        $this->messageBus->dispatch(new AttemptDelivery($delivery->getUuid()), [new DelayStamp($delayInSeconds * 1000)]);
    }
}
