<?php

namespace App\Delivery;

use App\Entity\EventIn;
use App\Entity\Target;
use App\Enum\DeliveryOutcome;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpTransport implements DeliveryTransport
{
    private const int MAX_DURATION_IN_SECONDS = 5;
    private const int RESPONSE_EXCERPT_MAX_LENGTH = 500;
    private const array RETRYABLE_CLIENT_ERROR_CODES = [408, 429];

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function deliver(EventIn $eventIn, Target $target, int $attemptNumber): DeliveryResult
    {
        $body = json_encode($eventIn->getPayload(), \JSON_THROW_ON_ERROR);

        $response = $this->httpClient->request('POST', $target->getConfig()['url'], [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Event-Id' => $eventIn->getUuid()->toRfc4122(),
                'X-Event-Type' => $eventIn->getType(),
                'X-Delivery-Attempt' => (string) $attemptNumber,
                'X-Signature' => hash_hmac('sha256', $body, $target->getSecret()),
            ],
            'body' => $body,
            'max_duration' => self::MAX_DURATION_IN_SECONDS,
        ]);

        // The client is lazy: timeouts and connection errors surface when the response is read.
        try {
            $responseCode = $response->getStatusCode();
            $responseBody = $response->getContent(false);
        } catch (TransportExceptionInterface $transportException) {
            return new DeliveryResult(DeliveryOutcome::RetryableFailure, failureReason: $transportException->getMessage());
        }

        return new DeliveryResult(
            $this->classifyResponseCode($responseCode),
            $responseCode,
            $this->extractResponseExcerpt($responseBody),
        );
    }

    private function classifyResponseCode(int $responseCode): DeliveryOutcome
    {
        if ($responseCode >= 200 && $responseCode < 300) {
            return DeliveryOutcome::Delivered;
        }

        if ($responseCode >= 500 || \in_array($responseCode, self::RETRYABLE_CLIENT_ERROR_CODES, true)) {
            return DeliveryOutcome::RetryableFailure;
        }

        return DeliveryOutcome::PermanentFailure;
    }

    private function extractResponseExcerpt(string $responseBody): ?string
    {
        if ('' === $responseBody) {
            return null;
        }

        return mb_substr($responseBody, 0, self::RESPONSE_EXCERPT_MAX_LENGTH);
    }
}
