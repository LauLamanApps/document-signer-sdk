<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Exception;

/**
 * The provider throttled the request (HTTP 429).
 *
 * Retryable — pause for at least `$retryAfterSeconds` (or a sensible
 * backoff when the header is missing) and try again.
 */
final class ProviderRateLimitException extends ProviderException
{
    public function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds = null,
        ?int $httpStatus = 429,
        ?string $providerCode = null,
        ?string $providerMessage = null,
        ?string $providerBody = null,
        ?\Throwable $previous = null,
        ?string $providerEnvelopeId = null,
    ) {
        parent::__construct(
            message: $message,
            httpStatus: $httpStatus,
            providerCode: $providerCode,
            providerMessage: $providerMessage,
            providerBody: $providerBody,
            previous: $previous,
            providerEnvelopeId: $providerEnvelopeId,
        );
    }

    public function isRetryable(): bool
    {
        return true;
    }

    public function withProviderEnvelopeId(string $providerEnvelopeId): static
    {
        if ($this->providerEnvelopeId === $providerEnvelopeId) {
            return $this;
        }

        return new self(
            message: $this->getMessage(),
            retryAfterSeconds: $this->retryAfterSeconds,
            httpStatus: $this->httpStatus,
            providerCode: $this->providerCode,
            providerMessage: $this->providerMessage,
            providerBody: $this->providerBody,
            previous: $this->getPrevious(),
            providerEnvelopeId: $providerEnvelopeId,
        );
    }
}
