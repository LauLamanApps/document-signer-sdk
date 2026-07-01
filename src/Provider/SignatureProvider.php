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
     * Download every signed document in the envelope as a ZIP archive.
     *
     * The response is materialised to a temp file on disk and returned as an
     * {@see \SplFileInfo} with a `.zip` extension. The archive contains one
     * signed PDF per {@see \LauLamanApps\DocumentSigner\Sdk\Document\Document}
     * in the original envelope, so callers can iterate signed documents in
     * their original order.
     *
     * Callers own the file lifecycle: unlink it, or copy the contents to a
     * durable location, when done. Nothing here removes it automatically.
     *
     * @throws ProviderException
     */
    public function downloadSigned(string $providerEnvelopeId): \SplFileInfo;

    /**
     * Download the provider's audit trail / evidence report for the envelope.
     *
     * Providers return different content shapes here, so the response is
     * materialised to a temp file on disk and returned as an {@see \SplFileInfo};
     * check `->getExtension()` to distinguish:
     *
     *  - DocuSign: `.json` — the envelope audit-events feed.
     *  - ValidSign: `.pdf` — the Evidence Summary Report.
     *
     * Callers own the file lifecycle: unlink it, or copy the contents to a
     * durable location, when done. Nothing here removes it automatically.
     *
     * @throws ProviderException
     */
    public function downloadAudit(string $providerEnvelopeId): \SplFileInfo;

    /**
     * Retrieve the values signers filled into every form field on the envelope.
     *
     * Providers return the same normalised `FieldValue` shape from their native
     * field-summary endpoint:
     *  - ValidSign: `GET /packages/{id}/fieldSummary`
     *  - DocuSign:  `GET /v2.1/accounts/{accountId}/envelopes/{envelopeId}/form_data`
     *
     * Useful for extracting structured data typed by a signer during signing
     * (e.g. an IBAN in a SEPA-mandate text field). Fields the signer left blank
     * come back with `$value === null`; fields on unfinished envelopes may be
     * missing entirely.
     *
     * @return list<FieldValue>
     * @throws ProviderException
     */
    public function getFieldValues(string $providerEnvelopeId): array;

    /**
     * Cancel / void an in-flight envelope.
     *
     * @throws ProviderException
     */
    public function cancel(string $providerEnvelopeId, ?string $reason = null): void;
}
