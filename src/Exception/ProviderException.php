<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Exception;

/**
 * Base type for every error surfaced by a {@see \LauLamanApps\DocumentSigner\Sdk\Provider\SignatureProvider}.
 *
 * Callers can `catch (ProviderException $e)` for a coarse "anything went wrong with
 * the provider" branch, or narrow with one of the subclasses when they want to
 * react by category:
 *
 *  - {@see ProviderValidationException}      — 4xx input/state problem; caller must fix and re-send.
 *  - {@see ProviderAuthenticationException}  — 401/403; credentials, JWT consent, or account access.
 *  - {@see ProviderNotFoundException}        — 404; the envelope/package id doesn't exist any more.
 *  - {@see ProviderRateLimitException}       — 429; retry after `$retryAfterSeconds`.
 *  - {@see ProviderTransientException}       — 5xx or transport failure; retry with backoff.
 *
 * The exception message is a short one-line summary. Structured detail is on
 * the readonly properties so log formatters and error handlers can render it
 * without regex-parsing the message.
 */
class ProviderException extends DocumentSignerException
{
    /**
     * @param string      $message            One-line summary, safe to display or log verbatim.
     * @param int|null    $httpStatus         HTTP response status when the provider replied; null for transport errors.
     * @param string|null $providerCode       Provider-native error code (e.g. `error.validation.invalidEmail`, `INVALID_EMAIL_ADDRESS`).
     * @param string|null $providerMessage    Human-readable message the provider returned.
     * @param string|null $providerBody       Raw response body (JSON, XML, or plain text) for debugging.
     * @param string|null $providerEnvelopeId Set when a provider-side envelope/package already exists — either
     *                                        because the provider echoed its id inside the error body, or because
     *                                        `send()` created one and a later step failed. Callers can use this
     *                                        id to poll status, download or cancel without a full re-send.
     */
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $providerCode = null,
        public readonly ?string $providerMessage = null,
        public readonly ?string $providerBody = null,
        ?\Throwable $previous = null,
        public readonly ?string $providerEnvelopeId = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Return a new exception of the same class with `providerEnvelopeId` populated.
     *
     * Providers call this when a `send()` succeeds at the API but the SDK then
     * fails to complete the flow — so the caller still gets the id it needs to
     * recover the envelope.
     */
    public function withProviderEnvelopeId(string $providerEnvelopeId): static
    {
        if ($this->providerEnvelopeId === $providerEnvelopeId) {
            return $this;
        }

        return new static(
            message: $this->getMessage(),
            httpStatus: $this->httpStatus,
            providerCode: $this->providerCode,
            providerMessage: $this->providerMessage,
            providerBody: $this->providerBody,
            previous: $this->getPrevious(),
            providerEnvelopeId: $providerEnvelopeId,
        );
    }

    /**
     * True when a retry — usually with backoff — is likely to succeed.
     *
     * Base implementations return false; {@see ProviderRateLimitException} and
     * {@see ProviderTransientException} override this.
     */
    public function isRetryable(): bool
    {
        return false;
    }

    /**
     * Pick and construct the right subclass for a given HTTP status.
     *
     * @param int|null    $retryAfterSeconds  Populated on rate-limit responses from the `Retry-After` header.
     * @param string|null $providerEnvelopeId Populated when the provider echoed an envelope/package id inside the error body.
     */
    public static function fromHttpStatus(
        string $providerName,
        string $method,
        string $path,
        int $status,
        ?string $providerCode = null,
        ?string $providerMessage = null,
        ?string $providerBody = null,
        ?\Throwable $previous = null,
        ?int $retryAfterSeconds = null,
        ?string $providerEnvelopeId = null,
    ): self {
        $summary = self::summarise($providerName, $method, $path, $status, $providerCode, $providerMessage);

        if ($status === 429) {
            return new ProviderRateLimitException(
                message: $summary,
                retryAfterSeconds: $retryAfterSeconds,
                httpStatus: $status,
                providerCode: $providerCode,
                providerMessage: $providerMessage,
                providerBody: $providerBody,
                previous: $previous,
                providerEnvelopeId: $providerEnvelopeId,
            );
        }

        $class = match (true) {
            $status === 401 || $status === 403 => ProviderAuthenticationException::class,
            $status === 404                    => ProviderNotFoundException::class,
            $status >= 400 && $status < 500    => ProviderValidationException::class,
            $status >= 500                     => ProviderTransientException::class,
            default                            => self::class,
        };

        return new $class(
            message: $summary,
            httpStatus: $status,
            providerCode: $providerCode,
            providerMessage: $providerMessage,
            providerBody: $providerBody,
            previous: $previous,
            providerEnvelopeId: $providerEnvelopeId,
        );
    }

    /**
     * Format the one-line summary. Kept package-private so subclasses reuse it.
     *
     * Example output:
     *  `ValidSign POST /packages [422 error.validation.invalidEmail]: The email field must be a valid email`
     */
    protected static function summarise(
        string $providerName,
        string $method,
        string $path,
        int $status,
        ?string $providerCode,
        ?string $providerMessage,
    ): string {
        $tag = '[' . $status . ($providerCode !== null && $providerCode !== '' ? ' ' . $providerCode : '') . ']';
        $head = sprintf('%s %s %s %s', $providerName, $method, self::displayPath($path), $tag);

        if ($providerMessage !== null && $providerMessage !== '') {
            return $head . ': ' . self::truncate($providerMessage, 300);
        }

        return $head;
    }

    private static function displayPath(string $path): string
    {
        return str_starts_with($path, '/') ? $path : '/' . ltrim($path, '/');
    }

    private static function truncate(string $text, int $limit): string
    {
        $collapsed = preg_replace('/\s+/', ' ', trim($text)) ?? $text;
        return strlen($collapsed) > $limit ? substr($collapsed, 0, $limit - 1) . '…' : $collapsed;
    }
}
