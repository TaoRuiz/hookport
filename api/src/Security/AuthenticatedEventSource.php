<?php

namespace App\Security;

use App\Entity\Source;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Security identity of a request signed by an event source. Symfony calls it a "user",
 * but it is an emitting application, never a person.
 */
final readonly class AuthenticatedEventSource implements UserInterface
{
    public const string ROLE = 'ROLE_EVENT_SOURCE';

    public function __construct(
        private Source $source,
    ) {
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function getUserIdentifier(): string
    {
        return $this->source->getSlug();
    }

    public function getRoles(): array
    {
        return [self::ROLE];
    }
}
