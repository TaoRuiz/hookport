<?php

namespace App\Tests;

use App\Entity\EventType;
use Doctrine\ORM\EntityManagerInterface;

trait CreatesEventTypes
{
    private function createEventType(string $slug, string $label, bool $isEnabled = true): EventType
    {
        $eventType = new EventType()
            ->setSlug($slug)
            ->setLabel($label)
            ->setEnabled($isEnabled);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($eventType);
        $entityManager->flush();

        return $eventType;
    }
}
