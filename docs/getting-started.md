# Getting started

This guide takes you from an empty checkout to a sent envelope.

## 1. Prerequisites

- **PHP 8.5** with the `mbstring`, `json` and `openssl` extensions.
- **Node.js 18+** and **Puppeteer**, required by `spatie/browsershot` to render
  HTML to PDF via headless Chromium.
- A sandbox account on at least one of:
  - [ValidSign](https://my.validsign.nl/) (Basic-auth API key)
  - [DocuSign demo](https://account-d.docusign.com/) (integration key, user GUID,
    RSA key pair, account GUID)

## 2. Install dependencies

The repository ships as a Composer monorepo. From the root:

```bash
composer install
```

For Browsershot, install Puppeteer once (either globally or per project):

```bash
npm install puppeteer
```

If Chromium is missing, run `node node_modules/puppeteer/install.mjs` from the
same directory.

## 3. Pick a provider and configure credentials

### ValidSign

```php
use LauLamanApps\DocumentSigner\ValidSign\ValidSignConfig;

$config = new ValidSignConfig(
    apiKey:          getenv('VALIDSIGN_API_KEY'),
    baseUrl:         'https://my.validsign.nl/api',
    defaultLanguage: 'nl',
);
```

### DocuSign

```php
use LauLamanApps\DocumentSigner\DocuSign\DocuSignConfig;

$config = new DocuSignConfig(
    integrationKey: getenv('DOCUSIGN_INTEGRATION_KEY'),
    userId:         getenv('DOCUSIGN_USER_ID'),
    accountId:      getenv('DOCUSIGN_ACCOUNT_ID'),
    privateKey:     file_get_contents('/path/to/private.pem'),
    oauthBaseUrl:   'account-d.docusign.com',
    apiBaseUrl:     'https://demo.docusign.net/restapi',
);
```

See the provider guides for the full setup story:

- [ValidSign provider](providers/validsign.md)
- [DocuSign provider](providers/docusign.md)

## 4. Build an envelope

```php
use LauLamanApps\DocumentSigner\Sdk\Document\Document;
use LauLamanApps\DocumentSigner\Sdk\Envelope\Envelope;
use LauLamanApps\DocumentSigner\Sdk\Signer\Signer;

$envelope = new Envelope(
    name:         'Mutual NDA',
    documents:    [
        new Document(
            id:   'nda',
            name: 'NDA',
            html: <<<HTML
                <h1>Mutual NDA</h1>
                <p>By signing below, {[text:counterparty:fullname]} agrees to the
                terms set out in this document.</p>
                <p>Signed: {[signature:counterparty:sig]} on {[date:counterparty:signdate]}</p>
            HTML,
        ),
    ],
    signers:      [
        new Signer(key: 'counterparty', name: 'Jane Doe', email: 'jane@example.com'),
    ],
    emailSubject: 'Please sign the NDA',
);
```

Every placeholder's middle segment (here `counterparty`) **must** match a
`Signer::$key`. The SDK validates this before contacting the provider and throws
a `ProviderException` if a placeholder references an unknown signer.

## 5. Send

```php
use LauLamanApps\DocumentSigner\ValidSign\ValidSignProvider;

$receipt = (new ValidSignProvider($config))->send($envelope);

echo $receipt->provider;           // "validsign"
echo $receipt->providerEnvelopeId; // e.g. "qZf2X1..."
echo $receipt->status->value;      // "sent"
```

## 6. Track status and retrieve the signed documents

`downloadSigned()`, `downloadSignedDocument()`, and `downloadAudit()` all
return an `\SplFileInfo` pointing at a temp file the SDK just wrote — you
own the file after the call:

```php
$status = $provider->getStatus($receipt->providerEnvelopeId);

if ($status->value === 'completed') {
    // All signed documents as a ZIP archive with one PDF per document in the
    // envelope, so multi-document envelopes stay separable.
    $archive = $provider->downloadSigned($receipt->providerEnvelopeId);
    rename($archive->getPathname(), storage_path('nda.zip'));

    // Or fetch a single document by its original Document::$id — no ZIP round-trip:
    $pdf = $provider->downloadSignedDocument($receipt->providerEnvelopeId, 'nda');
    rename($pdf->getPathname(), storage_path('nda.pdf'));

    // Audit / evidence: DocuSign returns `.json`, ValidSign returns `.pdf`.
    // Gate the download on hasAuditTrail() so consumers of a hypothetical
    // future audit-less provider degrade gracefully.
    if ($provider->hasAuditTrail()) {
        $audit = $provider->downloadAudit($receipt->providerEnvelopeId);
        rename($audit->getPathname(), storage_path('nda-audit.' . $audit->getExtension()));
    }
}
```

If you want per-document PDFs out of the archive, open it with `ZipArchive`:

```php
$zip = new ZipArchive();
$zip->open($archive->getPathname());
$zip->extractTo(storage_path('signed/'));
$zip->close();
```

### Extract signed field data

For structured data the signer typed during signing (SEPA IBAN, free-text
answers, checkbox selections), use `getFieldValues()`:

```php
foreach ($provider->getFieldValues($receipt->providerEnvelopeId) as $field) {
    if ($field->fieldName === 'iban' && $field->value !== null) {
        SepaMandate::create([
            'iban'         => $field->value,
            'document_id'  => $field->documentId,
            'signer_key'   => $field->signerKey,
        ]);
    }
}
```

Each `FieldValue` carries the placeholder's `fieldName`, the document/signer
identifiers, and the signer-typed `value`. Fields that were optional and left
blank come back with `value === null`. See the provider guides for the exact
endpoint each provider hits.

The status enum is normalised across providers — see
[`EnvelopeStatus`](../src/Envelope/EnvelopeStatus.php). Provider-specific
status names are mapped in each provider's guide.

## Next steps

- [Architecture](architecture.md) explains why the SDK is structured this way.
- [Placeholder syntax](placeholders.md) lists every supported field type.
- [Extending the SDK](extending.md) shows how to write a third provider.
