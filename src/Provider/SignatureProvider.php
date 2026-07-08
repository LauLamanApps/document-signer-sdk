<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Provider;

use LauLamanApps\DocumentSigner\Sdk\Envelope\Envelope;
use LauLamanApps\DocumentSigner\Sdk\Envelope\EnvelopeStatus;
use LauLamanApps\DocumentSigner\Sdk\Exception\ProviderException;
use LauLamanApps\DocumentSigner\Sdk\Exception\SignedDocumentUnavailableException;

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
     * Download the signed PDF for a single document in the envelope.
     *
     * `$documentId` is **always the id the caller assigned to the
     * {@see \LauLamanApps\DocumentSigner\Sdk\Document\Document::$id} when building
     * the envelope** — the same value across every provider. Implementations map
     * it to whatever their API actually needs (ValidSign uses it verbatim;
     * DocuSign resolves it to its own positional document id), and hide archive
     * quirks such as DocuSign's `Summary.pdf` certificate and its
     * space-to-underscore filename mangling. Callers therefore never have to
     * pull the whole ZIP and match filenames themselves.
     *
     * Useful when the caller only needs one document from a multi-document
     * envelope and wants to skip the ZIP round-trip. Response is materialised
     * to a temp file on disk with a `.pdf` extension; the caller owns the
     * lifecycle.
     *
     * @throws SignedDocumentUnavailableException When no signed document matches
     *         `$documentId` yet — typically the envelope isn't finalized. This is
     *         retryable ({@see SignedDocumentUnavailableException::isRetryable()});
     *         back off and try again.
     * @throws ProviderException For any other provider-side failure (auth, the
     *         envelope id itself missing, transport, ...).
     */
    public function downloadSignedDocument(string $providerEnvelopeId, string $documentId): \SplFileInfo;

    /**
     * Whether this provider exposes a machine-readable audit trail through
     * {@see downloadAudit()}.
     *
     * Both first-party providers (ValidSign, DocuSign) return `true`. The
     * flag exists for consumers to gate audit-trail UI (a download button,
     * a scheduled evidence-archival job) at composition time — implementations
     * without an audit-trail endpoint should return `false` and throw from
     * `downloadAudit()`, so calling code can decide up-front instead of
     * catching an exception.
     */
    public function hasAuditTrail(): bool;

    /**
     * Download the provider's human-readable evidence report for the envelope —
     * the completion certificate summarising who signed, when, from where, and
     * how they were authenticated. Both first-party providers return a `.pdf`:
     *
     *  - DocuSign: the **Certificate of Completion**.
     *  - ValidSign: the **Evidence Summary Report**.
     *
     * The response is materialised to a temp file on disk and returned as an
     * {@see \SplFileInfo}. Callers own the file lifecycle: unlink it, or copy the
     * contents to a durable location, when done. Nothing here removes it
     * automatically.
     *
     * (DocuSign's lower-level, machine-readable `audit_events` JSON feed is a
     * different artifact; reach for `DocuSignClient::downloadAuditEventsJson()`
     * directly if you need it.)
     *
     * Only call this when {@see hasAuditTrail()} returns `true`; providers
     * without an audit-trail endpoint throw a {@see ProviderException} here.
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
