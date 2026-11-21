<?php

namespace App\Demo\DataFixtures;

use App\Entity\EventType;
use App\Entity\Mapping;
use App\Entity\Source;
use App\Entity\Target;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class MappingFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [SourceFixtures::class, TargetFixtures::class, EventTypeFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $this->createMapping($manager, TargetFixtures::BILLING, SourceFixtures::PAYMENT_PROVIDER, EventTypeFixtures::PAYMENT_SUCCEEDED);
        $this->createMapping($manager, TargetFixtures::TEAM_CHANNEL, SourceFixtures::PAYMENT_PROVIDER, EventTypeFixtures::PAYMENT_FAILED, maxAttempts: 3);
        $this->createMapping($manager, TargetFixtures::BILLING, SourceFixtures::ONLINE_SHOP, EventTypeFixtures::ORDER_CREATED);
        $this->createMapping($manager, TargetFixtures::LOGISTICS, SourceFixtures::ONLINE_SHOP, EventTypeFixtures::ORDER_CREATED, maxAttempts: 8);
        $this->createMapping($manager, TargetFixtures::LOGISTICS, SourceFixtures::ONLINE_SHOP, null);
        $this->createMapping($manager, TargetFixtures::TEAM_CHANNEL, null, EventTypeFixtures::ORDER_SHIPPED);
        $this->createMapping($manager, TargetFixtures::ANALYTICS, null, null, isEnabled: false);
        $this->createMapping($manager, TargetFixtures::TEAM_CHANNEL, SourceFixtures::CRM, EventTypeFixtures::CUSTOMER_UPDATED, isEnabled: false);

        $manager->flush();
    }

    /**
     * A null source or event type reference means "any".
     */
    private function createMapping(
        ObjectManager $manager,
        string $targetReference,
        ?string $sourceReference,
        ?string $eventTypeReference,
        int $maxAttempts = Mapping::DEFAULT_MAX_ATTEMPTS,
        bool $isEnabled = true,
    ): void {
        $mapping = new Mapping()
            ->setTarget($this->getReference($targetReference, Target::class))
            ->setSource(null === $sourceReference ? null : $this->getReference($sourceReference, Source::class))
            ->setEventType(null === $eventTypeReference ? null : $this->getReference($eventTypeReference, EventType::class))
            ->setMaxAttempts($maxAttempts)
            ->setEnabled($isEnabled);

        $manager->persist($mapping);
    }
}
