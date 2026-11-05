<?php

namespace App\Security;

use App\Entity\Source;
use App\Repository\SourceRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticates an incoming event by its X-Source header and the HMAC-SHA256 of the raw body
 * in X-Signature, computed with the source secret.
 */
final class EventSourceSignatureAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly SourceRepository $sourceRepository,
    ) {
    }

    public function supports(Request $request): bool
    {
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $sourceSlug = $request->headers->get('X-Source');
        $signature = $request->headers->get('X-Signature');

        if (!$sourceSlug || !$signature) {
            throw new CustomUserMessageAuthenticationException('Missing X-Source or X-Signature header.');
        }

        $source = $this->sourceRepository->findOneBy(['slug' => $sourceSlug]);

        // Same answer for an unknown source and a wrong signature, so slugs cannot be probed.
        if (null === $source || !$this->isSignatureValid($request->getContent(), $signature, $source)) {
            throw new CustomUserMessageAuthenticationException('Invalid source or signature.');
        }

        if (!$source->isEnabled()) {
            throw new CustomUserMessageAccountStatusException('Source is disabled.');
        }

        return new SelfValidatingPassport(
            new UserBadge($sourceSlug, fn () => new AuthenticatedEventSource($source)),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $statusCode = $exception instanceof AccountStatusException
            ? Response::HTTP_FORBIDDEN
            : Response::HTTP_UNAUTHORIZED;

        return new JsonResponse(['error' => $exception->getMessageKey()], $statusCode);
    }

    private function isSignatureValid(string $rawBody, string $signature, Source $source): bool
    {
        $expectedSignature = hash_hmac('sha256', $rawBody, $source->getSecret());

        return hash_equals($expectedSignature, $signature);
    }
}
