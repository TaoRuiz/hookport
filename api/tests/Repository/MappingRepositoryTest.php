<?php

namespace App\Tests\Repository;

use App\Entity\Mapping;
use App\Entity\Target;
use App\Repository\MappingRepository;
use App\Tests\CreatesEventTypes;
use App\Tests\CreatesMappings;
use App\Tests\CreatesSources;
use App\Tests\CreatesTargets;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MappingRepositoryTest extends KernelTestCase
{
    use CreatesEventTypes;
    use CreatesMappings;
    use CreatesSources;
    use CreatesTargets;

    private MappingRepository $mappingRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->mappingRepository = static::getContainer()->get(MappingRepository::class);
    }

    public function testMappingForTheSameSourceAndEventTypeMatches(): void
    {
        $source = $this->createSource('emitter-app', 'emitter-shared-secret');
        $eventType = $this->createEventType('record.created', 'Record created');
        $mapping = $this->createMapping($this->createReceiver(), $source, $eventType);

        $matchingMappings = $this->mappingRepository->findEnabledMappingsMatching($source, 'record.created');

        self::assertSame([$mapping->getId()], $this->idsOf($matchingMappings));
    }

    public function testWildcardSourceAndWildcardEventTypeMatchAnyEvent(): void
    {
        $source = $this->createSource('emitter-app', 'emitter-shared-secret');
        $this->createEventType('record.created', 'Record created');
        $mapping = $this->createMapping($this->createReceiver());

        $matchingMappings = $this->mappingRepository->findEnabledMappingsMatching($source, 'record.created');

        self::assertSame([$mapping->getId()], $this->idsOf($matchingMappings));
    }

    public function testMappingForAnotherSourceOrAnotherEventTypeDoesNotMatch(): void
    {
        $source = $this->createSource('emitter-app', 'emitter-shared-secret');
        $otherSource = $this->createSource('other-app', 'other-shared-secret');
        $this->createEventType('record.created', 'Record created');
        $otherEventType = $this->createEventType('record.deleted', 'Record deleted');
        $receiver = $this->createReceiver();
        $this->createMapping($receiver, $otherSource);
        $this->createMapping($receiver, eventType: $otherEventType);

        $matchingMappings = $this->mappingRepository->findEnabledMappingsMatching($source, 'record.created');

        self::assertSame([], $matchingMappings);
    }

    public function testDisabledMappingDoesNotMatch(): void
    {
        $source = $this->createSource('emitter-app', 'emitter-shared-secret');
        $this->createMapping($this->createReceiver(), $source, isEnabled: false);

        $matchingMappings = $this->mappingRepository->findEnabledMappingsMatching($source, 'record.created');

        self::assertSame([], $matchingMappings);
    }

    public function testDisabledEventTypeIsNotRoutedEvenByWildcardMappings(): void
    {
        $source = $this->createSource('emitter-app', 'emitter-shared-secret');
        $disabledEventType = $this->createEventType('record.created', 'Record created', isEnabled: false);
        $receiver = $this->createReceiver();
        $this->createMapping($receiver, $source, $disabledEventType);
        $this->createMapping($receiver);

        $matchingMappings = $this->mappingRepository->findEnabledMappingsMatching($source, 'record.created');

        self::assertSame([], $matchingMappings);
    }

    public function testEventTypeMissingFromTheCatalogMatchesWildcardEventTypeMappingsOnly(): void
    {
        $source = $this->createSource('emitter-app', 'emitter-shared-secret');
        $cataloguedEventType = $this->createEventType('record.created', 'Record created');
        $receiver = $this->createReceiver();
        $this->createMapping($receiver, $source, $cataloguedEventType);
        $wildcardMapping = $this->createMapping($receiver, $source);

        $matchingMappings = $this->mappingRepository->findEnabledMappingsMatching($source, 'record.unknown');

        self::assertSame([$wildcardMapping->getId()], $this->idsOf($matchingMappings));
    }

    public function testDisabledTargetStillMatchesSoItsDeliveryCanFailVisibly(): void
    {
        $source = $this->createSource('emitter-app', 'emitter-shared-secret');
        $disabledTarget = $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret', isEnabled: false);
        $mapping = $this->createMapping($disabledTarget, $source);

        $matchingMappings = $this->mappingRepository->findEnabledMappingsMatching($source, 'record.created');

        self::assertSame([$mapping->getId()], $this->idsOf($matchingMappings));
    }

    private function createReceiver(): Target
    {
        return $this->createTarget('Receiver', 'https://receiver.example.test', 'target-shared-secret');
    }

    /**
     * @param list<Mapping> $mappings
     *
     * @return list<int|null>
     */
    private function idsOf(array $mappings): array
    {
        return array_map(static fn (Mapping $mapping): ?int => $mapping->getId(), $mappings);
    }
}
