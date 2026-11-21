<?php

namespace App\DataFixtures;

use App\Entity\Target;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TargetFixtures extends Fixture
{
    public const string BILLING = 'target-billing';
    public const string LOGISTICS = 'target-logistics';
    public const string TEAM_CHANNEL = 'target-team-channel';
    public const string ANALYTICS = 'target-analytics';

    private const string DEMO_BASE_URL = 'http://localhost:8000/demo';

    public function load(ObjectManager $manager): void
    {
        $this->createTarget($manager, self::BILLING, 'Billing', self::DEMO_BASE_URL.'/billing');
        $this->createTarget($manager, self::LOGISTICS, 'Logistics', self::DEMO_BASE_URL.'/logistics');
        $this->createTarget($manager, self::TEAM_CHANNEL, 'Team channel', self::DEMO_BASE_URL.'/team-channel');
        $this->createTarget($manager, self::ANALYTICS, 'Analytics warehouse', self::DEMO_BASE_URL.'/analytics', isEnabled: false);

        $manager->flush();
    }

    private function createTarget(ObjectManager $manager, string $reference, string $name, string $url, bool $isEnabled = true): void
    {
        $target = new Target()
            ->setName($name)
            ->setConfig(['url' => $url])
            ->setSecret(bin2hex(random_bytes(32)))
            ->setEnabled($isEnabled);

        $manager->persist($target);
        $this->addReference($reference, $target);
    }
}
