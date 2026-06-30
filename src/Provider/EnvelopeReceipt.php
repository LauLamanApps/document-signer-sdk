<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Provider;

use LauLamanApps\DocumentSigner\Sdk\Envelope\EnvelopeStatus;

final readonly class EnvelopeReceipt
{
    /**
     * @param string                       $provider           Identifier of the provider that produced this receipt
     *                                                         (`validsign`, `docusign`, or whatever a custom implementation returns).
     * @param string                       $providerEnvelopeId Provider-assigned id (DocuSign envelopeId, ValidSign packageId).
     * @param EnvelopeStatus               $status             Normalised status at the moment of return.
     * @param array<string, string>        $signerUrls         Map of `Signer::$key` to signing URL when the provider returns inline links.
     * @param array<string, scalar|null|array<mixed>> $raw     Untouched provider response payload for debugging.
     */
    public function __construct(
        public string         $provider,
        public string         $providerEnvelopeId,
        public EnvelopeStatus $status,
        public array          $signerUrls = [],
        public array          $raw = [],
    ) {
        if ($provider === '') {
            throw new \InvalidArgumentException('EnvelopeReceipt provider must be non-empty.');
        }
        if ($providerEnvelopeId === '') {
            throw new \InvalidArgumentException('EnvelopeReceipt providerEnvelopeId must be non-empty.');
        }
    }
}
