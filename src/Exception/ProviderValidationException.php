<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Exception;

/**
 * The provider rejected the request because of an input or state problem
 * (HTTP 4xx that isn't specifically auth, not-found or rate-limit).
 *
 * Typical causes: invalid signer email, missing required field, envelope not
 * in a state that permits the requested transition. Not retryable — the
 * caller must fix the input and re-send.
 */
final class ProviderValidationException extends ProviderException
{
}
