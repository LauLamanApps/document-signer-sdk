# ValidSign provider

`laulamanapps/document-signer-validsign` ships a `SignatureProvider` implementation backed by
[ValidSign](https://www.validsign.eu/)'s REST API and its native
[text-tag](https://validsign.zendesk.com/hc/nl/articles/360037747091-Text-tags-gebruiken-binnen-documenten)
field-placement mechanism.

## Credentials

ValidSign uses a single long-lived API key per tenant, sent as
`Authorization: Basic <api-key>`. To generate one:

1. Log in to your ValidSign tenant.
2. Open **Admin > Integration > API Access**.
3. Create or copy an API key. Store it as `VALIDSIGN_API_KEY`.

The tenant base URL is your ValidSign hostname plus `/api`, for example
`https://my.validsign.nl/api`. The staging environment uses a different
hostname — check with your account manager.

## Configuration

```php
use LauLamanApps\DocumentSigner\ValidSign\ValidSignConfig;

$config = new ValidSignConfig(
    apiKey:               getenv('VALIDSIGN_API_KEY'),
    baseUrl:              'https://my.validsign.nl/api',
    defaultLanguage:      'nl',
    timeoutSeconds:       15,
    uploadTimeoutSeconds: 60,
);
```

| Argument | Required | Default | Notes |
| --- | :-: | --- | --- |
| `apiKey` | yes | — | Base64-encoded API key shown in the ValidSign UI. |
| `baseUrl` | no | `https://my.validsign.nl/api` | Tenant-specific. Must include `/api`. |
| `defaultLanguage` | no | `nl` | Applied to signers that don't override `Signer::$language`. |
| `timeoutSeconds` | no | `15` | HTTP timeout for status/download/cancel. |
| `uploadTimeoutSeconds` | no | `60` | HTTP timeout for envelope creation. |

## Sending an envelope

```php
use LauLamanApps\DocumentSigner\Sdk\Document\Document;
use LauLamanApps\DocumentSigner\Sdk\Envelope\Envelope;
use LauLamanApps\DocumentSigner\Sdk\Signer\Signer;
use LauLamanApps\DocumentSigner\Sdk\Signer\SigningOrder;
use LauLamanApps\DocumentSigner\ValidSign\ValidSignProvider;

$envelope = new Envelope(
    name:         'Sales agreement',
    documents:    [
        new Document(
            id:   'sa',
            name: 'Sales agreement',
            html: <<<HTML
                <h1>Sales agreement</h1>
                <p>I, {[text:customer:fullname]}, accept the offer.</p>
                <p>{[signature:customer:sig]} on {[date:customer:signdate]}</p>
            HTML,
        ),
    ],
    signers:      [
        new Signer(key: 'customer', name: 'Jane Doe', email: 'jane@example.com'),
    ],
    emailSubject: 'Please sign the sales agreement',
    signingOrder: SigningOrder::Parallel,
);

$provider = new ValidSignProvider($config);
$receipt  = $provider->send($envelope);

echo $receipt->provider;           // "validsign" (also: ValidSignProvider::NAME)
echo $receipt->providerEnvelopeId; // ValidSign packageId
```

## Status mapping

| ValidSign status | SDK `EnvelopeStatus` |
| --- | --- |
| `DRAFT` | `Draft` |
| `SENT` | `Sent` |
| `COMPLETED` | `Completed` |
| `ARCHIVED` | `Completed` |
| `DECLINED` | `Declined` |
| `OPTED_OUT` | `Declined` |
| `EXPIRED` | `Expired` |
| anything else | `Unknown` |

## Endpoint mapping

| SDK call | HTTP |
| --- | --- |
| `send()` | `POST /packages` (multipart: JSON `payload` + one `file` part per PDF) |
| `getStatus()` | `GET /packages/{id}` |
| `downloadSigned()` | `GET /packages/{id}/documents/zip` — returns a ZIP with one signed PDF per document in the package. Materialised to a temp file, returned as `\SplFileInfo` with a `.zip` extension. |
| `downloadSignedDocument()` | `GET /packages/{id}/documents/{documentId}` — returns the signed PDF for a single document by its `Document::$id`. Materialised to a temp file, returned as `\SplFileInfo` with a `.pdf` extension. |
| `hasAuditTrail()` | Constant `true` — ValidSign always ships the Evidence Summary Report. |
| `downloadAudit()` | `GET /packages/{id}/evidence/summary` — returns the Evidence Summary Report as a PDF. Materialised to a temp file, returned as `\SplFileInfo` with a `.pdf` extension. |
| `getFieldValues()` | `GET /packages/{id}/fieldSummary` — returns each filled form-field value as a list of `FieldValue` DTOs (`documentId`, `signerKey`, `fieldName`, `value`). Use this to pull data typed during signing (e.g. an IBAN in a SEPA-mandate text field). |
| `cancel()` | `DELETE /packages/{id}` |

## Field mapping

Each SDK `FieldType` becomes a native ValidSign
[text-tag](https://validsign.zendesk.com/hc/nl/articles/360037747091-Text-tags-gebruiken-binnen-documenten)
in the rendered PDF. `<name>` is the placeholder's field name; `SignerN` is
the signer's positional index in `Envelope::$signers` (1-based).

| `FieldType` | Emitted text-tag | Default W × H |
| --- | --- | --- |
| `Signature` | `{{esl_<name>:SignerN:Signature:size(200,50)}}` | 200 × 50 |
| `Initials` | `{{esl_<name>:SignerN:initials:size(100,30)}}` | 100 × 30 |
| `Text` | `{{*esl_<name>:SignerN:TextField:size(200,20)}}` | 200 × 20 |
| `Date` | `{{esl_<name>:SignerN:SigningDate:size(120,20)}}` (auto-populated by ValidSign) | 120 × 20 |
| `Checkbox` | `{{*esl_<name>:SignerN:Checkbox:size(20,20)}}` | 20 × 20 |

The `*` prefix marks a field as required. Signatures and initials are
implicitly required per ValidSign so no prefix is applied; `SigningDate` is
auto-populated when the signer signs.

`documents[].extract = true` is set on every uploaded PDF; ValidSign detects
these tags server-side and places the fields on the corresponding signer, so
the create-package payload doesn't need an `approvals` / `fields` block.

## Sequential signing

When `Envelope::$signingOrder` is `SigningOrder::Sequential`, every role gets
`index = Signer::$order - 1`. Parallel signing collapses everyone to `index = 0`.

## Injecting a custom HTTP client

The Guzzle client can be swapped out (useful for tests, middleware, custom
TLS settings):

```php
use LauLamanApps\DocumentSigner\ValidSign\Http\ValidSignClient;

$client = new ValidSignClient($config, http: new \GuzzleHttp\Client([
    'base_uri' => $config->trimmedBaseUrl() . '/',
    'timeout'  => 30,
    'handler'  => $stackWithMiddleware,
    'headers'  => [
        'Authorization' => 'Basic ' . $config->apiKey,
        'Accept'        => 'application/json',
    ],
]));

$provider = new ValidSignProvider($config, client: $client);
```

## Troubleshooting

- **`401 Unauthorized`**: API key wrong, expired, or missing the `Basic `
  prefix. Sanity-check by curling `GET /packages?from=0&to=1` with the same key.
- **Field placed on wrong page / not extracted**: the text-tag
  `{{esl_…:SignerN:…}}` didn't survive the PDF text layer. Run
  `pdftotext yourfile.pdf -` and grep for `esl_`. If absent, see
  [PDF rendering](../pdf-rendering.md).
- **Field placed on the wrong signer**: `SignerN` is positional in the API's
  `roles[]` array (which mirrors `Envelope::$signers` order). If a signer is
  configured before the intended one in the envelope, the tag will resolve
  to the earlier signer. Reorder `$envelope->signers` accordingly.
- **`Aanhalingstekens Word` quote errors when uploading**: ValidSign's tag
  parser rejects Word's "smart quotes". If you generate the HTML from a Word
  document or a rich-text editor, normalise `“…”` to plain `"..."` before
  passing to the SDK.
- **`Document references unknown signer key`**: a placeholder references a
  `signerKey` you forgot to add to `Envelope::$signers`.
