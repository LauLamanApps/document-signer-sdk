<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Tests\Exception;

use LauLamanApps\DocumentSigner\Sdk\Exception\ProviderAuthenticationException;
use LauLamanApps\DocumentSigner\Sdk\Exception\ProviderException;
use LauLamanApps\DocumentSigner\Sdk\Exception\ProviderNotFoundException;
use LauLamanApps\DocumentSigner\Sdk\Exception\ProviderRateLimitException;
use LauLamanApps\DocumentSigner\Sdk\Exception\ProviderTransientException;
use LauLamanApps\DocumentSigner\Sdk\Exception\ProviderValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProviderExceptionTest extends TestCase
{
    #[Test]
    #[DataProvider('statusMappings')]
    public function it_selects_the_right_subclass_from_the_http_status(int $status, string $expectedClass, bool $expectedRetryable): void
    {
        $e = ProviderException::fromHttpStatus(
            providerName: 'ValidSign',
            method: 'POST',
            path: '/packages',
            status: $status,
        );

        self::assertInstanceOf($expectedClass, $e);
        self::assertSame($expectedRetryable, $e->isRetryable());
        self::assertSame($status, $e->httpStatus);
    }

    /**
     * @return iterable<string, array{int, class-string, bool}>
     */
    public static function statusMappings(): iterable
    {
        yield '400 validation' => [400, ProviderValidationException::class, false];
        yield '422 validation' => [422, ProviderValidationException::class, false];
        yield '401 auth'       => [401, ProviderAuthenticationException::class, false];
        yield '403 auth'       => [403, ProviderAuthenticationException::class, false];
        yield '404 not found'  => [404, ProviderNotFoundException::class, false];
        yield '429 rate limit' => [429, ProviderRateLimitException::class, true];
        yield '500 transient'  => [500, ProviderTransientException::class, true];
        yield '503 transient'  => [503, ProviderTransientException::class, true];
    }

    #[Test]
    public function the_summary_message_leads_with_provider_status_code_and_message(): void
    {
        $e = ProviderException::fromHttpStatus(
            providerName: 'ValidSign',
            method: 'POST',
            path: 'packages',
            status: 422,
            providerCode: 'error.validation.invalidEmail',
            providerMessage: 'The email field must be a valid email',
        );

        self::assertSame(
            'ValidSign POST /packages [422 error.validation.invalidEmail]: The email field must be a valid email',
            $e->getMessage(),
        );
    }

    #[Test]
    public function rate_limit_exceptions_carry_the_retry_after_seconds(): void
    {
        $e = ProviderException::fromHttpStatus(
            providerName: 'DocuSign',
            method: 'GET',
            path: '/envelopes/x',
            status: 429,
            retryAfterSeconds: 30,
        );

        self::assertInstanceOf(ProviderRateLimitException::class, $e);
        self::assertSame(30, $e->retryAfterSeconds);
    }

    #[Test]
    public function with_provider_envelope_id_returns_the_same_subclass_carrying_the_id(): void
    {
        /** @var ProviderRateLimitException $e */
        $e = ProviderException::fromHttpStatus(
            providerName: 'DocuSign',
            method: 'GET',
            path: '/x',
            status: 429,
            retryAfterSeconds: 5,
        );

        $withId = $e->withProviderEnvelopeId('env-abc');

        self::assertInstanceOf(ProviderRateLimitException::class, $withId);
        self::assertSame('env-abc', $withId->providerEnvelopeId);
        self::assertSame(5, $withId->retryAfterSeconds, 'rate-limit metadata survives the copy');
    }

    #[Test]
    public function long_provider_messages_are_truncated_in_the_summary_but_preserved_in_the_field(): void
    {
        $long = str_repeat('word ', 200);

        $e = ProviderException::fromHttpStatus(
            providerName: 'ValidSign',
            method: 'POST',
            path: '/packages',
            status: 422,
            providerMessage: $long,
        );

        self::assertStringEndsWith('…', $e->getMessage());
        self::assertSame($long, $e->providerMessage, 'raw field is preserved verbatim');
    }
}
