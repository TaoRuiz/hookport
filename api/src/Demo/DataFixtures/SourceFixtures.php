<?php

namespace App\Demo\DataFixtures;

use App\Entity\Source;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SourceFixtures extends Fixture
{
    public const string ONLINE_SHOP = 'online-shop';
    public const string PAYMENT_PROVIDER = 'payment-provider';
    public const string CRM = 'crm';
    public const string LEGACY_ERP = 'legacy-erp';

    public function load(ObjectManager $manager): void
    {
        $this->createSource($manager, self::ONLINE_SHOP, 'Online shop');
        $this->createSource($manager, self::PAYMENT_PROVIDER, 'Payment provider');
        $this->createSource($manager, self::CRM, 'CRM');
        $this->createSource($manager, self::LEGACY_ERP, 'Legacy ERP', isEnabled: false);

        $manager->flush();
    }

    private function createSource(ObjectManager $manager, string $slug, string $name, bool $isEnabled = true): void
    {
        $source = new Source($slug)
            ->setName($name)
            ->setSecret(bin2hex(random_bytes(32)))
            ->setEnabled($isEnabled);

        $manager->persist($source);
        $this->addReference($slug, $source);
    }
}
