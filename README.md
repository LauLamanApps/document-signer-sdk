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

## Contents

- [Packages](#packages)
- [Framework integrations](#framework-integrations)
- [Fluent builder](#fluent-builder)
- [Page decoration](#page-decoration)
- [End-to-end example](#end-to-end-example)
- [Documentation](#documentation)
- [Requirements](#requirements)

## Packages

| Package | Path | Purpose |
| --- | --- | --- |
| `laulamanapps/document-signer-sdk` | `sdk/` | Domain model, placeholder parser, anchor-replacer base, Browsershot PDF renderer, `SignatureProvider` contract. |
| `laulamanapps/document-signer-validsign` | `validsign/` | ValidSign implementation. |
| `laulamanapps/document-signer-docusign` | `docusign/` | DocuSign eSignature implementation. |

All three are installed together for local development through the root
`composer.json`, which exposes them as `path` repositories.

## Framework integrations

Rather than wiring the SDK by hand, two packages integrate it into the popular
PHP frameworks. Each gives you a container-managed `DocumentSignerManager` that
resolves a `SignatureProvider` by name, verified webhook routes, and a signer-
email **recipient override** for dev/staging. They mirror each other — same
providers-list config, same webhook event — so the two stay conceptually
identical:

| Package | Repository | Purpose |
| --- | --- | --- |
| `laulamanapps/document-signer-symfony` | [document-signer-symfony](https://github.com/LauLamanApps/document-signer-symfony) | Bundle: configuration, driver manager, verified webhook routes, translated event labels. |
| `laulamanapps/document-signer-laravel` | [document-signer-laravel](https://github.com/LauLamanApps/document-signer-laravel) | Config, service provider, driver manager, facade, Blade placeholder components, verified webhook routes. |

Install the one matching your framework:

```bash
composer require laulamanapps/document-signer-laravel   # Laravel
composer require laulamanapps/document-signer-symfony   # Symfony
```

## Fluent builder

The positional `new Envelope(...)` constructor still works, but for
step-by-step assembly there's a builder:

```php
$envelope = Envelope::builder()
    ->name('NDA 2026-06')
    ->emailSubject('Please sign the NDA')
    ->emailMessage('Hi Jane, please sign at your convenience.')
    ->addDocument($document)
    ->addSigner($signer)
    ->signingOrder(SigningOrder::Parallel)
    ->withMetadata('contract_id', 42)
    ->build();
```

`addSigner()` and `addDocument()` each accept **either** a pre-built value
object **or** the raw constructor arguments via named parameters, so you can
mix styles depending on what reads best at each call site:

```php
Envelope::builder()
    ->name('NDA')
    ->emailSubject('Please sign')
    // Pre-built object:
    ->addDocument(new Document(id: 'nda', name: 'NDA', html: $html))
    // Inline named args:
    ->addSigner(key: 'counterparty', name: 'Jane Doe', email: 'jane@example.com')
    ->build();
```

`build()` delegates every domain invariant (duplicate signer keys, empty
document list, etc.) to `Envelope`'s constructor — so a half-configured
builder never blows up mid-chain.

## Page decoration

`Document` accepts optional header and footer HTML with per-document
placement:

```php
new Document(
    id:   'nda',
    name: 'NDA',
    html: '<h1>NDA</h1>...',
    headerHtml: '<div class="brand">Acme Legal</div>',
    footerHtml: '<div>Confidential</div>',
    headerPlacement: HeaderPlacement::FirstPage,   // or ::AllPages
    footerPlacement: FooterPlacement::AllPages,
);
```

Under the hood the SDK's `PageDecoration` is threaded through to the PDF
renderer. `AllPages` uses the underlying engine's native repeat-on-every-page
header/footer slot; `FirstPage` inlines the HTML at the top/bottom of the
body (since browsers have no native "first-page only" flag). See
[PDF rendering](docs/pdf-rendering.md) for the details.

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
- [Error handling](docs/errors.md)
- [Webhook events](docs/webhook-events.md)
- [Symfony integration](https://github.com/LauLamanApps/document-signer-symfony)
- [Laravel integration](https://github.com/LauLamanApps/document-signer-laravel)

## Requirements

- PHP 8.5
- A PDF renderer. The SDK bundles a `BrowsershotPdfRenderer` that wraps
  [spatie/browsershot](https://github.com/spatie/browsershot), but that
  package is an *optional* dependency you must install explicitly if you
  want to use the default:
  ```bash
  composer require spatie/browsershot
  ```
  Constructing `BrowsershotPdfRenderer` without it throws a clear
  install-hint. To use a different engine, implement `PdfRenderer` yourself
  (see [PDF rendering](docs/pdf-rendering.md)).
- Node.js + Puppeteer (only when using the Browsershot renderer — for headless Chromium)
- A ValidSign or DocuSign account with API credentials
