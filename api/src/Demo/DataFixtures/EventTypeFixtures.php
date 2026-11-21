<?php

namespace App\Demo\DataFixtures;

use App\Entity\EventType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class EventTypeFixtures extends Fixture
{
    public const string ORDER_CREATED = 'order.created';
    public const string ORDER_SHIPPED = 'order.shipped';
    public const string PAYMENT_SUCCEEDED = 'payment.succeeded';
    public const string PAYMENT_FAILED = 'payment.failed';
    public const string CUSTOMER_UPDATED = 'customer.updated';
    public const string CART_ABANDONED = 'cart.abandoned';

    public function load(ObjectManager $manager): void
    {
        $this->createEventType($manager, self::ORDER_CREATED, 'Order created');
        $this->createEventType($manager, self::ORDER_SHIPPED, 'Order shipped');
        $this->createEventType($manager, self::PAYMENT_SUCCEEDED, 'Payment succeeded');
        $this->createEventType($manager, self::PAYMENT_FAILED, 'Payment failed');
        $this->createEventType($manager, self::CUSTOMER_UPDATED, 'Customer updated');
        $this->createEventType($manager, self::CART_ABANDONED, 'Cart abandoned', isEnabled: false);

        $manager->flush();
    }

    private function createEventType(ObjectManager $manager, string $slug, string $label, bool $isEnabled = true): void
    {
        $eventType = new EventType($slug)
            ->setLabel($label)
            ->setEnabled($isEnabled);

        $manager->persist($eventType);
        $this->addReference($slug, $eventType);
    }
}
