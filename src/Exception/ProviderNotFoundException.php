<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Exception;

/**
 * The provider could not find the envelope / package id (HTTP 404).
 *
 * Typical causes: envelope was already deleted / archived past the retention
 * window, id was mis-stored, or the request went to the wrong account. Not
 * retryable.
 */
final class ProviderNotFoundException extends ProviderException
{
}
