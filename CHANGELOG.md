# Changelog

All notable changes to `laulamanapps/document-signer-sdk` are documented here.
This project adheres to [Semantic Versioning](https://semver.org/).

## [2.3.0] - 2026-07-08

### Added

- `SignedDocumentUnavailableException` — a **retryable** `ProviderException`
  (`isRetryable() === true`) thrown by
  `SignatureProvider::downloadSignedDocument()` when a single signed document
  cannot be produced for the requested id yet (typically the envelope isn't
  finalized). Distinct from `ProviderNotFoundException` (the envelope id is
  gone — not retryable).

### Changed — `downloadSignedDocument()` contract

The `$documentId` argument of `SignatureProvider::downloadSignedDocument()` is
now defined as **always the caller's `Document::$id`**, uniform across every
provider. Implementations map it to whatever their API needs and hide archive
quirks (DocuSign's positional ids, its `Summary.pdf` certificate, and its
space-to-underscore filename mangling), so consumers never reach into a
provider's ZIP to find a single document. This was already the documented
intent; it is now guaranteed by every first-party provider.

### Changed — `downloadAudit()` is uniformly a human-readable evidence PDF

`SignatureProvider::downloadAudit()` is now defined to return the provider's
human-readable completion certificate as a `.pdf` for every provider (DocuSign:
Certificate of Completion; ValidSign: Evidence Summary Report). Previously the
DocuSign implementation returned a `.json` audit-events feed — see the DocuSign
package changelog.

### Changed

- Minimum PHP lowered to **8.2** (was 8.5); CI now tests 8.2–8.5.
