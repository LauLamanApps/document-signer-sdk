<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Provider;

use LauLamanApps\DocumentSigner\Sdk\Envelope\Envelope;
use LauLamanApps\DocumentSigner\Sdk\Envelope\EnvelopeStatus;
use LauLamanApps\DocumentSigner\Sdk\Exception\ProviderException;

interface SignatureProvider
{
    /**
     * Render every document in the envelope, register it with the provider and put it in `sent` state.
     *
     * @throws ProviderException When the provider rejects the request or the renderer fails.
     */
    public function send(Envelope $envelope): EnvelopeReceipt;

    /**
     * Fetch the current status of a previously created envelope.
     *
     * @throws ProviderException
     */
    public function getStatus(string $providerEnvelopeId): EnvelopeStatus;

    /**
     * Download the signed (and, where applicable, certificate-of-completion-merged) PDF.
     *
     * @return string Raw PDF bytes.
     * @throws ProviderException
     */
    public function downloadSigned(string $providerEnvelopeId): string;

    /**
     * Cancel / void an in-flight envelope.
     *
     * @throws ProviderException
     */
    public function cancel(string $providerEnvelopeId, ?string $reason = null): void;
}
