# Document Signer SDK

A provider-agnostic PHP SDK for sending HTML documents through e-signature providers.
You author contracts as HTML with `{[type:signer:name]}` placeholders; the SDK
translates them to the provider's native anchor format, renders the HTML to PDF,
and creates the envelope.

```
+-------------------+        +--------------------+        +----------------------+
|  HTML + signers   |  -->   |  documentsigner    |  -->   |  ValidSign /         |
|  (your code)      |        |  sdk + provider    |        |  DocuSign API        |
+-------------------+        +--------------------+        +----------------------+
```

## Packages

| Package | Path | Purpose |
| --- | --- | --- |
| `laulamanapps/document-signer-sdk` | `sdk/` | Domain model, placeholder parser, anchor-replacer base, Browsershot PDF renderer, `SignatureProvider` contract. |
| `laulamanapps/document-signer-validsign` | `validsign/` | ValidSign (OneSpan Sign) implementation. |
| `laulamanapps/document-signer-docusign` | `docusign/` | DocuSign eSignature implementation. |

All three are installed together for local development through the root
`composer.json`, which exposes them as `path` repositories.

## End-to-end example

```php
use LauLamanApps\DocumentSigner\Sdk\Document\Document;
use LauLamanApps\DocumentSigner\Sdk\Envelope\Envelope;
use LauLamanApps\DocumentSigner\Sdk\Signer\Signer;
use LauLamanApps\DocumentSigner\Sdk\Signer\SigningOrder;
use LauLamanApps\DocumentSigner\ValidSign\ValidSignConfig;
use LauLamanApps\DocumentSigner\ValidSign\ValidSignProvider;

$envelope = new Envelope(
    name:         'NDA 2026-06',
    documents:    [
        new Document(
            id:   'nda',
            name: 'Non-disclosure agreement',
            html: '<h1>NDA</h1><p>Signed by {[text:counterparty:fullname]}</p>'
                . '<p>{[signature:counterparty:sig]} on {[date:counterparty:signdate]}</p>',
        ),
    ],
    signers:      [
        new Signer(key: 'counterparty', name: 'Jane Doe', email: 'jane@example.com'),
    ],
    emailSubject: 'Please sign the NDA',
    emailMessage: 'Hi Jane, please sign at your convenience.',
    signingOrder: SigningOrder::Parallel,
);

$provider = new ValidSignProvider(
    new ValidSignConfig(apiKey: getenv('VALIDSIGN_API_KEY')),
);

$receipt = $provider->send($envelope);
// $receipt->providerEnvelopeId is now the ValidSign package id.
```

Swapping providers is a one-line change: instantiate `DocuSignProvider` with a
`DocuSignConfig` instead — the `Envelope` is untouched.

## Documentation

Start with [Getting started](docs/getting-started.md), then dive into the
relevant guide:

- [Getting started](docs/getting-started.md)
- [Architecture](docs/architecture.md)
- [Placeholder syntax](docs/placeholders.md)
- [PDF rendering](docs/pdf-rendering.md)
- [ValidSign provider](docs/providers/validsign.md)
- [DocuSign provider](docs/providers/docusign.md)
- [Extending the SDK](docs/extending.md)
- [Writing a custom provider](docs/custom-provider.md)

## Requirements

- PHP 8.5
- Node.js + Puppeteer (required by `spatie/browsershot` for HTML→PDF)
- A ValidSign or DocuSign account with API credentials
