<?php

namespace App\Tests;

use App\Entity\EventType;
use App\Entity\Mapping;
use App\Entity\Source;
use App\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;

trait CreatesMappings
{
    private function createMapping(
        Target $target,
        ?Source $source = null,
        ?EventType $eventType = null,
        int $maxAttempts = Mapping::DEFAULT_MAX_ATTEMPTS,
        bool $isEnabled = true,
    ): Mapping {
        $mapping = new Mapping()
            ->setTarget($target)
            ->setSource($source)
            ->setEventType($eventType)
            ->setMaxAttempts($maxAttempts)
            ->setEnabled($isEnabled);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($mapping);
        $entityManager->flush();

        return $mapping;
    }
}
