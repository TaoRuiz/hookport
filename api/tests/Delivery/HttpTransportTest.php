<?php

namespace App\Tests\Delivery;

use App\Delivery\HttpTransport;
use App\Entity\EventIn;
use App\Entity\Source;
use App\Entity\Target;
use App\Enum\DeliveryOutcome;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpTransportTest extends TestCase
{
    private const string TARGET_SECRET = 'target-shared-secret';

    public function testEventIsPostedToTheTargetUrlWithSignedHeaders(): void
    {
        $targetResponse = new MockResponse('', ['http_code' => 200]);
        $eventIn = $this->createEvent();

        new HttpTransport(new MockHttpClient($targetResponse))->deliver($eventIn, $this->createTarget(), 3);

        $expectedBody = '{"field":"value"}';
        self::assertSame('POST', $targetResponse->getRequestMethod());
        self::assertSame('https://receiver.example.test/hook', $targetResponse->getRequestUrl());
        self::assertSame($expectedBody, $targetResponse->getRequestOptions()['body']);
        self::assertContains('Content-Type: application/json', $targetResponse->getRequestOptions()['headers']);
        self::assertContains('X-Event-Id: '.$eventIn->getUuid()->toRfc4122(), $targetResponse->getRequestOptions()['headers']);
        self::assertContains('X-Event-Type: record.created', $targetResponse->getRequestOptions()['headers']);
        self::assertContains('X-Delivery-Attempt: 3', $targetResponse->getRequestOptions()['headers']);
        self::assertContains('X-Signature: '.hash_hmac('sha256', $expectedBody, self::TARGET_SECRET), $targetResponse->getRequestOptions()['headers']);
    }

    public function testSuccessfulResponseIsReportedWithItsCodeAndExcerpt(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('received', ['http_code' => 202]));

        $deliveryResult = new HttpTransport($httpClient)->deliver($this->createEvent(), $this->createTarget(), 1);

        self::assertSame(DeliveryOutcome::Delivered, $deliveryResult->outcome);
        self::assertSame(202, $deliveryResult->responseCode);
        self::assertSame('received', $deliveryResult->responseExcerpt);
    }

    public static function failingResponseCodes(): iterable
    {
        yield 'server error' => [500, DeliveryOutcome::RetryableFailure];
        yield 'service unavailable' => [503, DeliveryOutcome::RetryableFailure];
        yield 'request timeout' => [408, DeliveryOutcome::RetryableFailure];
        yield 'too many requests' => [429, DeliveryOutcome::RetryableFailure];
        yield 'bad request' => [400, DeliveryOutcome::PermanentFailure];
        yield 'not found' => [404, DeliveryOutcome::PermanentFailure];
    }

    #[DataProvider('failingResponseCodes')]
    public function testFailingResponseCodeIsClassified(int $responseCode, DeliveryOutcome $expectedOutcome): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => $responseCode]));

        $deliveryResult = new HttpTransport($httpClient)->deliver($this->createEvent(), $this->createTarget(), 1);

        self::assertSame($expectedOutcome, $deliveryResult->outcome);
        self::assertSame($responseCode, $deliveryResult->responseCode);
    }

    public static function unansweredRequestErrors(): iterable
    {
        yield 'timeout' => ['Idle timeout reached for "https://receiver.example.test/hook".'];
        yield 'connection error' => ['Could not resolve host "receiver.example.test".'];
    }

    #[DataProvider('unansweredRequestErrors')]
    public function testUnansweredRequestIsRetryableWithoutResponseCode(string $transportError): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['error' => $transportError]));

        $deliveryResult = new HttpTransport($httpClient)->deliver($this->createEvent(), $this->createTarget(), 1);

        self::assertSame(DeliveryOutcome::RetryableFailure, $deliveryResult->outcome);
        self::assertNull($deliveryResult->responseCode);
        self::assertNull($deliveryResult->responseExcerpt);
        self::assertSame($transportError, $deliveryResult->failureReason);
    }

    public function testLongResponseBodyIsTruncated(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(str_repeat('a', 600), ['http_code' => 500]));

        $deliveryResult = new HttpTransport($httpClient)->deliver($this->createEvent(), $this->createTarget(), 1);

        self::assertSame(500, mb_strlen($deliveryResult->responseExcerpt));
    }

    private function createEvent(): EventIn
    {
        return new EventIn(new Source('emitter-app'), 'evt_1', 'record.created', ['field' => 'value']);
    }

    private function createTarget(): Target
    {
        return new Target()
            ->setName('Receiver')
            ->setConfig(['url' => 'https://receiver.example.test/hook'])
            ->setSecret(self::TARGET_SECRET);
    }
}
