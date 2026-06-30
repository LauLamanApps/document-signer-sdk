# Architecture

## Three packages, one mental model

The SDK is split into three Composer packages with strict directional
dependencies:

```
            +------------------------+
            |  laulamanapps/documentsigner-sdk    |   shared contracts + domain + PDF
            +------------------------+
                ^                ^
                |                |
+---------------+--+         +---+---------------+
| validsign       |         | docusign         |
| ValidSignProvider|         | DocuSignProvider  |
+------------------+         +-------------------+
```

- `sdk/` knows nothing about ValidSign or DocuSign.
- `validsign/` and `docusign/` depend on `sdk/`. They never depend on each
  other.

This is what makes the API stable for callers and replaceable for implementers:
your application code talks to `LauLamanApps\DocumentSigner\Sdk\Provider\SignatureProvider`,
not to a specific vendor.

## Request flow

```
+--------------+    parse     +-----------------+
| Document.html| -----------> | ParsedPlaceholder[]
+--------------+              +-----------------+
                                       |
                                       | replace()
                                       v
                              +----------------+      +---------------+
                              | PreparedDoc:   |      | provider tabs |
                              |  - html        |----->| /             |
                              |  - fields[]    |      | extractAnchors|
                              +----------------+      +---------------+
                                       |
                                       | PdfRenderer::render()
                                       v
                              +----------------+      +---------------+
                              |     PDF bytes  |----->|  HTTP API     |
                              +----------------+      +---------------+
```

For every document in an envelope the provider:

1. Calls `PlaceholderParser::parse()` on the HTML and gets a list of
   `ParsedPlaceholder` records.
2. Calls its own `PlaceholderReplacer::replace()` (a subclass of
   `AbstractAnchorPlaceholderReplacer`) which:
   - swaps each placeholder for a near-invisible inline `<span>` containing an
     anchor token,
   - emits a `PreparedField` per placeholder linking the anchor token to the
     SDK's `FieldType` / signer key / field name.
3. Calls `PdfRenderer::render()` to convert the prepared HTML to PDF bytes.
4. Builds the provider-specific request: signers/recipients, plus one anchor
   tab per `PreparedField`, and uploads the PDFs.

## Why anchor strings?

Both ValidSign and DocuSign support "anchor tagging": you upload a PDF and tell
the API "find this string in the document and position the field here." This
avoids having to compute (page, x, y) coordinates ourselves and survives any
layout changes in the HTML template.

The SDK's job is therefore to:

- Insert a unique anchor string at every placeholder location.
- Keep that string in the PDF text layer but visually hidden from the human
  reader (white 1pt text, no layout impact).
- Tell the provider which anchor string corresponds to which field on which
  signer.

This shared logic lives in `LauLamanApps\DocumentSigner\Sdk\Placeholder\AbstractAnchorPlaceholderReplacer`.
Provider packages only override `formatAnchor()`.

## Domain model

| Type | Lives in | Purpose |
| --- | --- | --- |
| `Envelope` | `Sdk\Envelope` | Top-level entity. Bundles documents, signers, subject, ordering, expiry, metadata. |
| `Document` | `Sdk\Document` | One PDF-to-be in an envelope. Holds the source HTML. |
| `Signer` | `Sdk\Signer` | Recipient. `key` matches the middle segment of placeholders. |
| `SigningOrder` | `Sdk\Signer` | `Parallel` or `Sequential`. Sequential uses `Signer::$order`. |
| `FieldType` | `Sdk\Field` | `Signature`, `Initials`, `Text`, `Date`, `Checkbox`. |
| `EnvelopeStatus` | `Sdk\Envelope` | Normalised lifecycle: `Draft`, `Sent`, `Delivered`, `Completed`, `Declined`, `Voided`, `Expired`, `Unknown`. |
| `EnvelopeReceipt` | `Sdk\Provider` | What `send()` returns: provider name (`validsign`/`docusign`/...), provider envelope id, status, raw payload. |

## Where the seams are

- **PDF renderer**: implements `PdfRenderer`. Default is
  `BrowsershotPdfRenderer`; swap it for any HTML-to-PDF strategy you like
  (wkhtmltopdf, Gotenberg, an external service).
- **Placeholder replacer**: subclass `AbstractAnchorPlaceholderReplacer` and
  override `formatAnchor()` if a provider needs a different anchor format. You
  can also override `wrapAnchor()` if the default `<span>` doesn't survive your
  PDF pipeline.
- **HTTP client**: every provider takes its `*Client` as a constructor
  argument, and every `*Client` takes a Guzzle `ClientInterface`. Inject a mock
  for tests or a custom client with middleware (retries, logging, tracing).

See [Extending the SDK](extending.md) for end-to-end examples of each seam.
