<?php

namespace App\Tests\Security;

use App\Tests\CreatesSources;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EventSourceAuthenticationTest extends WebTestCase
{
    use CreatesSources;

    private const string SOURCE_SLUG = 'emitter-app';
    private const string SOURCE_SECRET = 'emitter-shared-secret';
    private const string RAW_BODY = '{"type":"record.created","id":"evt_1","payload":{}}';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testCorrectlySignedEventIsAcceptedWithoutSession(): void
    {
        $this->createSource(self::SOURCE_SLUG, self::SOURCE_SECRET);

        $this->postEvent(self::SOURCE_SLUG, $this->sign(self::RAW_BODY, self::SOURCE_SECRET));

        self::assertResponseStatusCodeSame(202);
        self::assertResponseNotHasCookie('MOCKSESSID');
    }

    public function testMissingSignatureIsRejected(): void
    {
        $this->createSource(self::SOURCE_SLUG, self::SOURCE_SECRET);

        $this->postEvent(self::SOURCE_SLUG, signature: null);

        self::assertResponseStatusCodeSame(401);
    }

    public function testUnknownSourceIsRejected(): void
    {
        $this->postEvent('unknown-source', $this->sign(self::RAW_BODY, self::SOURCE_SECRET));

        self::assertResponseStatusCodeSame(401);
    }

    public function testInvalidSignatureIsRejected(): void
    {
        $this->createSource(self::SOURCE_SLUG, self::SOURCE_SECRET);

        $this->postEvent(self::SOURCE_SLUG, $this->sign(self::RAW_BODY, 'wrong-secret'));

        self::assertResponseStatusCodeSame(401);
    }

    public function testSignatureOfAnotherBodyIsRejected(): void
    {
        $this->createSource(self::SOURCE_SLUG, self::SOURCE_SECRET);

        $this->postEvent(self::SOURCE_SLUG, $this->sign('{"tampered":true}', self::SOURCE_SECRET));

        self::assertResponseStatusCodeSame(401);
    }

    public function testDisabledSourceIsForbidden(): void
    {
        $this->createSource(self::SOURCE_SLUG, self::SOURCE_SECRET, isEnabled: false);

        $this->postEvent(self::SOURCE_SLUG, $this->sign(self::RAW_BODY, self::SOURCE_SECRET));

        self::assertResponseStatusCodeSame(403);
    }

    public function testDisabledSourceWithInvalidSignatureIsUnauthorizedNotForbidden(): void
    {
        $this->createSource(self::SOURCE_SLUG, self::SOURCE_SECRET, isEnabled: false);

        $this->postEvent(self::SOURCE_SLUG, $this->sign(self::RAW_BODY, 'wrong-secret'));

        self::assertResponseStatusCodeSame(401);
    }

    private function sign(string $rawBody, string $secret): string
    {
        return hash_hmac('sha256', $rawBody, $secret);
    }

    private function postEvent(string $sourceSlug, ?string $signature): void
    {
        $headers = ['HTTP_X_SOURCE' => $sourceSlug, 'CONTENT_TYPE' => 'application/json'];

        if (null !== $signature) {
            $headers['HTTP_X_SIGNATURE'] = $signature;
        }

        $this->client->request('POST', '/api/events', server: $headers, content: self::RAW_BODY);
    }
}
