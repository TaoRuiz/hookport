<?php

namespace App\Controller;

use App\Dto\EventIngestionRequest;
use App\Entity\EventIn;
use App\Security\AuthenticatedEventSource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class IngestEventController extends AbstractController
{
    #[Route('/api/events', name: 'api_ingest_event', methods: ['POST'])]
    public function ingestEvent(
        #[MapRequestPayload] EventIngestionRequest $eventIngestionRequest,
        #[CurrentUser] AuthenticatedEventSource $authenticatedEventSource,
        EntityManagerInterface $entityManager,
    ): Response {
        $eventIn = new EventIn(
            $authenticatedEventSource->getSource(),
            $eventIngestionRequest->externalId,
            $eventIngestionRequest->type,
            $eventIngestionRequest->payload,
        );
        $entityManager->persist($eventIn);
        $entityManager->flush();

        return new Response(status: Response::HTTP_ACCEPTED);
    }
}
