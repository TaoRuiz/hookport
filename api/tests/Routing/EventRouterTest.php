<?php

namespace App\Tests\Routing;

use App\Entity\Delivery;
use App\Entity\EventIn;
use App\Entity\Source;
use App\Enum\DeliveryStatus;
use App\Repository\DeliveryRepository;
use App\Routing\EventRouter;
use App\Tests\CreatesEventTypes;
use App\Tests\CreatesMappings;
use App\Tests\CreatesSources;
use App\Tests\CreatesTargets;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class EventRouterTest extends KernelTestCase
{
    use CreatesEventTypes;
    use CreatesMappings;
    use CreatesSources;
    use CreatesTargets;

    private EventRouter $eventRouter;
    private EntityManagerInterface $entityManager;
    private Source $source;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->eventRouter = static::getContainer()->get(EventRouter::class);
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->source = $this->createSource('emitter-app', 'emitter-shared-secret');
    }

    public function testCreatesOnePendingDeliveryPerMatchingTargetAndNoneForOthers(): void
    {
        $eventType = $this->createEventType('record.created', 'Record created');
        $firstTarget = $this->createTarget('First target', 'https://first.example.test', 'first-secret');
        $secondTarget = $this->createTarget('Second target', 'https://second.example.test', 'second-secret');
        $unrelatedTarget = $this->createTarget('Unrelated target', 'https://unrelated.example.test', 'unrelated-secret');
        $this->createMapping($firstTarget, $this->source, $eventType, maxAttempts: 3);
        $this->createMapping($secondTarget);
        $this->createMapping($unrelatedTarget, $this->createSource('other-app', 'other-shared-secret'));

        $deliveries = $this->routeEvent('record.created');

        self::assertEqualsCanonicalizing(['First target', 'Second target'], $this->targetNamesOf($deliveries));
        foreach ($deliveries as $delivery) {
            self::assertSame(DeliveryStatus::Pending, $delivery->getStatus());
            self::assertSame(0, $delivery->getAttemptsCount());
        }
        self::assertSame(2, static::getContainer()->get(DeliveryRepository::class)->count());
    }

    public function testEventWithoutMatchingMappingGetsNoDelivery(): void
    {
        $this->createMapping(
            $this->createTarget('Receiver', 'https://receiver.example.test', 'target-secret'),
            eventType: $this->createEventType('record.deleted', 'Record deleted'),
        );

        self::assertSame([], $this->routeEvent('record.created'));
    }

    public function testOverlappingMappingsToTheSameTargetCreateASingleDelivery(): void
    {
        $eventType = $this->createEventType('record.created', 'Record created');
        $receiver = $this->createTarget('Receiver', 'https://receiver.example.test', 'target-secret');
        $this->createMapping($receiver);
        $this->createMapping($receiver, $this->source);
        $this->createMapping($receiver, eventType: $eventType);
        $this->createMapping($receiver, $this->source, $eventType);

        self::assertCount(1, $this->routeEvent('record.created'));
    }

    public function testMostSpecificMappingGivesItsMaxAttempts(): void
    {
        $eventType = $this->createEventType('record.created', 'Record created');
        $receiver = $this->createTarget('Receiver', 'https://receiver.example.test', 'target-secret');
        $this->createMapping($receiver, maxAttempts: 10);
        $this->createMapping($receiver, $this->source, maxAttempts: 8);
        $this->createMapping($receiver, $this->source, $eventType, maxAttempts: 2);

        $deliveries = $this->routeEvent('record.created');

        self::assertSame(2, $deliveries[0]->getMaxAttempts());
    }

    public function testSourceOnlyAgainstEventTypeOnlyKeepsTheHighestMaxAttempts(): void
    {
        $eventType = $this->createEventType('record.created', 'Record created');
        $receiver = $this->createTarget('Receiver', 'https://receiver.example.test', 'target-secret');
        $this->createMapping($receiver, $this->source, maxAttempts: 3);
        $this->createMapping($receiver, eventType: $eventType, maxAttempts: 7);

        $deliveries = $this->routeEvent('record.created');

        self::assertSame(7, $deliveries[0]->getMaxAttempts());
    }

    public function testChangingTheMappingMaxAttemptsDoesNotAffectExistingDeliveries(): void
    {
        $mapping = $this->createMapping($this->createTarget('Receiver', 'https://receiver.example.test', 'target-secret'), maxAttempts: 3);
        $delivery = $this->routeEvent('record.created')[0];

        $mapping->setMaxAttempts(9);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(3, static::getContainer()->get(DeliveryRepository::class)->find($delivery->getUuid())->getMaxAttempts());
    }

    /**
     * @return list<Delivery>
     */
    private function routeEvent(string $eventTypeSlug): array
    {
        $eventIn = new EventIn($this->source, 'evt_1', $eventTypeSlug, ['field' => 'value']);
        $this->entityManager->persist($eventIn);
        $deliveries = $this->eventRouter->createDeliveriesFor($eventIn);
        $this->entityManager->flush();

        return $deliveries;
    }

    /**
     * @param list<Delivery> $deliveries
     *
     * @return list<string|null>
     */
    private function targetNamesOf(array $deliveries): array
    {
        return array_map(static fn (Delivery $delivery): ?string => $delivery->getTarget()->getName(), $deliveries);
    }
}
