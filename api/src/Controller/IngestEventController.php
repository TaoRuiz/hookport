<?php

namespace App\Controller;

use App\Dto\EventIngestionRequest;
use App\Entity\EventIn;
use App\Message\AttemptDelivery;
use App\Repository\EventInRepository;
use App\Routing\EventRouter;
use App\Security\AuthenticatedEventSource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class IngestEventController extends AbstractController
{
    #[Route('/api/events', name: 'api_ingest_event', methods: ['POST'])]
    public function ingestEvent(
        #[MapRequestPayload] EventIngestionRequest $eventIngestionRequest,
        #[CurrentUser] AuthenticatedEventSource $authenticatedEventSource,
        EntityManagerInterface $entityManager,
        EventInRepository $eventInRepository,
        EventRouter $eventRouter,
        MessageBusInterface $messageBus,
    ): Response {
        $source = $authenticatedEventSource->getSource();
        $isAlreadyReceived = null !== $eventInRepository->findOneBy([
            'source' => $source,
            'externalId' => $eventIngestionRequest->externalId,
        ]);
        if ($isAlreadyReceived) {
            return new Response(status: Response::HTTP_OK);
        }

        $eventIn = new EventIn(
            $source,
            $eventIngestionRequest->externalId,
            $eventIngestionRequest->type,
            $eventIngestionRequest->payload,
        );
        $entityManager->persist($eventIn);
        $deliveries = $eventRouter->createDeliveriesFor($eventIn);
        $entityManager->flush();

        foreach ($deliveries as $delivery) {
            $messageBus->dispatch(new AttemptDelivery($delivery->getUuid()));
        }

        return new Response(status: Response::HTTP_ACCEPTED);
    }
}
