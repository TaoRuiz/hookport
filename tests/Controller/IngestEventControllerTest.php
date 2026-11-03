<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class IngestEventControllerTest extends WebTestCase
{
    public function testEventIsAcceptedWithoutDashboardSession(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/events');

        self::assertResponseStatusCodeSame(202);
        self::assertResponseNotHasCookie('MOCKSESSID');
    }
}
