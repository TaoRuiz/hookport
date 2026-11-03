<?php

namespace App\Controller;

use App\Dto\EventIngestionRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

final class IngestEventController extends AbstractController
{
    #[Route('/api/events', name: 'api_ingest_event', methods: ['POST'])]
    public function ingestEvent(#[MapRequestPayload] EventIngestionRequest $eventIngestionRequest): Response
    {
        return new Response(status: Response::HTTP_ACCEPTED);
    }
}
