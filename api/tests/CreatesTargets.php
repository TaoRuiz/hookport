<?php

namespace App\Tests;

use App\Entity\Target;
use Doctrine\ORM\EntityManagerInterface;

trait CreatesTargets
{
    private function createTarget(string $name, string $url, string $secret, bool $isEnabled = true): Target
    {
        $target = new Target()
            ->setName($name)
            ->setConfig(['url' => $url])
            ->setSecret($secret)
            ->setEnabled($isEnabled);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($target);
        $entityManager->flush();

        return $target;
    }
}
