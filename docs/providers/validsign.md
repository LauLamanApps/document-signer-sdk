# ValidSign provider

`laulamanapps/documentsigner-validsign` ships a `SignatureProvider` implementation backed by
the [ValidSign / OneSpan Sign REST API](https://my.validsign.nl/).

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
| `downloadAudit()` | `GET /packages/{id}/evidence/summary` — returns the Evidence Summary Report as a PDF. Materialised to a temp file, returned as `\SplFileInfo` with a `.pdf` extension. |
| `cancel()` | `DELETE /packages/{id}` |

## Field mapping

The provider translates each `FieldType` into the ValidSign field shape:

| `FieldType` | ValidSign `type` / `subtype` | Default width × height (px) |
| --- | --- | --- |
| `Signature` | `SIGNATURE` / `FULLNAME` | 150 × 50 |
| `Initials` | `SIGNATURE` / `INITIALS` | 150 × 50 |
| `Text` | `INPUT` / `TEXTFIELD` | 180 × 20 |
| `Date` | `INPUT` / `LABEL` (binding `{approval.signed}`) | 120 × 20 |
| `Checkbox` | `INPUT` / `CHECKBOX` | 20 × 20 |

Anchor extraction is enabled per document (`extract: true`) and each field
carries an `extractAnchor` block pointing at the unique
`[[VS:type:signer:name]]` token rendered into the PDF.

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
- **Field placed on wrong page / not extracted**: the anchor `[[VS:...]]`
  didn't survive the PDF text layer. Run `pdftotext yourfile.pdf -` and grep
  for the token. If absent, see [PDF rendering](../pdf-rendering.md).
- **`Document references unknown signer key`**: a placeholder references a
  `signerKey` you forgot to add to `Envelope::$signers`.
