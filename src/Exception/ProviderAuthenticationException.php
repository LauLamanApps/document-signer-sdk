<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Exception;

/**
 * The provider rejected the credentials or refused the operation on
 * authorisation grounds (HTTP 401 / 403).
 *
 * Typical causes: wrong API key, expired/rotated JWT signing key, missing
 * user consent grant, account-scoped operation on the wrong account. Not
 * retryable — check the config and (for DocuSign) that the impersonated user
 * has granted consent.
 */
final class ProviderAuthenticationException extends ProviderException
{
}
