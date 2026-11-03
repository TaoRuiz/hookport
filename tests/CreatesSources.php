<?php

namespace App\Tests;

use App\Entity\Source;
use Doctrine\ORM\EntityManagerInterface;

trait CreatesSources
{
    private function createSource(string $slug, string $secret, bool $isEnabled = true): Source
    {
        $source = new Source()
            ->setSlug($slug)
            ->setName(ucfirst($slug))
            ->setSecret($secret)
            ->setEnabled($isEnabled);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($source);
        $entityManager->flush();

        return $source;
    }
}
