<?php

namespace App\MessageHandler;

use App\Delivery\DeliveryTransport;
use App\Entity\DeliveryAttempt;
use App\Enum\DeliveryOutcome;
use App\Enum\DeliveryStatus;
use App\Message\AttemptDelivery;
use App\Repository\DeliveryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AttemptDeliveryHandler
{
    public function __construct(
        private DeliveryRepository $deliveryRepository,
        private DeliveryTransport $deliveryTransport,
        private EntityManagerInterface $entityManager,
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
        $deliveryResult = $this->deliveryTransport->deliver($delivery->getEventIn(), $delivery->getTarget(), $attemptNumber);
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
        $delivery
            ->setAttemptsCount($attemptNumber)
            ->setStatus(match ($deliveryResult->outcome) {
                DeliveryOutcome::Delivered => DeliveryStatus::Delivered,
                DeliveryOutcome::PermanentFailure => DeliveryStatus::Failed,
            });
        $this->entityManager->flush();
    }
}
