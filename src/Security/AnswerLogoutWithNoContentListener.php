<?php

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class AnswerLogoutWithNoContentListener
{
    // Runs before Symfony's DefaultLogoutListener (priority 64), which would redirect instead.
    #[AsEventListener(priority: 65)]
    public function answerWithNoContent(LogoutEvent $event): void
    {
        $event->setResponse(new Response(status: Response::HTTP_NO_CONTENT));
    }
}
