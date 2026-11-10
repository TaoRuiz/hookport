<?php

namespace App\Tests\Controller;

use App\Tests\CreatesEventTypes;
use App\Tests\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EventTypeManagementControllerTest extends WebTestCase
{
    use CreatesEventTypes;
    use CreatesUsers;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testDemoSeesEveryEventTypeSortedByLabelIncludingDisabledOnes(): void
    {
        $enabledEventType = $this->createEventType('record.updated', 'Record updated');
        $disabledEventType = $this->createEventType('record.created', 'Record created', isEnabled: false);
        $this->client->loginUser($this->createUser('demo', ['ROLE_DEMO']));

        $this->client->request('GET', '/api/event-types');

        self::assertResponseIsSuccessful();
        self::assertSame(
            [
                ['id' => $disabledEventType->getId(), 'slug' => 'record.created', 'label' => 'Record created', 'enabled' => false],
                ['id' => $enabledEventType->getId(), 'slug' => 'record.updated', 'label' => 'Record updated', 'enabled' => true],
            ],
            json_decode($this->client->getResponse()->getContent(), true),
        );
    }

    public function testAnonymousVisitorCannotListEventTypes(): void
    {
        $this->client->request('GET', '/api/event-types');

        self::assertResponseStatusCodeSame(401);
    }
}
