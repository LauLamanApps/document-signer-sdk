<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Exception;

/**
 * A single signed document could not be produced for the requested id.
 *
 * Thrown by {@see \LauLamanApps\DocumentSigner\Sdk\Provider\SignatureProvider::downloadSignedDocument()}
 * when the provider cannot resolve the caller's `Document::$id` to a finalized,
 * downloadable PDF. The two everyday causes are:
 *
 *  - the envelope hasn't finished signing yet, so the signed PDF isn't ready; or
 *  - the document isn't listed on the envelope yet (eventual consistency right
 *    after `send()`).
 *
 * Both clear up on their own, so this is {@see isRetryable()} `true` — callers
 * should back off and try again rather than treat it as fatal. A genuinely
 * wrong id will, of course, keep failing; give up after a bounded number of
 * attempts. It is distinct from {@see ProviderNotFoundException} (the *envelope*
 * id is gone — not retryable).
 */
final class SignedDocumentUnavailableException extends ProviderException
{
    public function isRetryable(): bool
    {
        return true;
    }

    /**
     * Build a clear, uniform exception across providers for "no signed PDF for
     * this document id (yet)".
     */
    public static function for(
        string $providerName,
        string $providerEnvelopeId,
        string $documentId,
        ?string $detail = null,
        ?\Throwable $previous = null,
    ): self {
        $message = sprintf(
            '%s: no signed document found for id "%s" on envelope "%s"%s',
            $providerName,
            $documentId,
            $providerEnvelopeId,
            $detail !== null && $detail !== '' ? ' (' . $detail . ')' : ' — it may not be finalized yet',
        );

        return new self(
            message: $message,
            previous: $previous,
            providerEnvelopeId: $providerEnvelopeId,
        );
    }
}
