<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Exception;

/**
 * The provider errored on its own side, or the request never landed at all.
 *
 * Covers 5xx responses and transport-level failures (connection timeout,
 * DNS, TLS). Retryable — apply exponential backoff and try again.
 */
final class ProviderTransientException extends ProviderException
{
    public function isRetryable(): bool
    {
        return true;
    }
}
