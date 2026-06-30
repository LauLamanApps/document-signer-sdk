# DocuSign provider

`laulamanapps/documentsigner-docusign` ships a `SignatureProvider` implementation backed by
the [DocuSign eSignature REST API v2.1](https://developers.docusign.com/docs/esign-rest-api/).

DocuSign uses the OAuth 2.0 JWT user-consent grant to mint short-lived access
tokens. The SDK handles that for you.

## Credentials

You need four things from the DocuSign Admin app:

1. **Integration key** (client id) — created under **Apps and Keys**.
2. **User GUID** — the API user that the integration impersonates.
3. **Account GUID** — the DocuSign account the envelopes belong to.
4. **RSA key pair** — generate on the integration key page; save the private
   key in PEM form.

### One-time user consent

The first time an integration key impersonates a user, DocuSign requires that
user to grant consent in a browser. Open:

```
https://account-d.docusign.com/oauth/auth
    ?response_type=code
    &scope=signature%20impersonation
    &client_id=YOUR_INTEGRATION_KEY
    &redirect_uri=https://www.docusign.com
```

(use `account.docusign.com` for production). Sign in as the user whose GUID
you'll use for `userId`. Approve. From then on `DocuSignJwtAuth` can mint
tokens without further interaction.

## Configuration

```php
use LauLamanApps\DocumentSigner\DocuSign\DocuSignConfig;

$config = new DocuSignConfig(
    integrationKey:        getenv('DOCUSIGN_INTEGRATION_KEY'),
    userId:                getenv('DOCUSIGN_USER_ID'),
    accountId:             getenv('DOCUSIGN_ACCOUNT_ID'),
    privateKey:            file_get_contents('/path/to/private.pem'),
    oauthBaseUrl:          'account-d.docusign.com',
    apiBaseUrl:            'https://demo.docusign.net/restapi',
    scopes:                'signature impersonation',
    accessTokenTtlSeconds: 3600,
);
```

| Argument | Required | Default | Notes |
| --- | :-: | --- | --- |
| `integrationKey` | yes | — | UUID from **Apps and Keys**. |
| `userId` | yes | — | GUID of the impersonated user. |
| `accountId` | yes | — | GUID of the DocuSign account. |
| `privateKey` | yes | — | PEM contents of the RSA private key (must contain `PRIVATE KEY`). |
| `oauthBaseUrl` | no | `account-d.docusign.com` | Use `account.docusign.com` in production. |
| `apiBaseUrl` | no | `https://demo.docusign.net/restapi` | Use the URL returned by the OAuth userinfo endpoint for production accounts (often `https://na3.docusign.net/restapi` or similar). |
| `scopes` | no | `signature impersonation` | Required scopes for the JWT grant. |
| `accessTokenTtlSeconds` | no | `3600` | DocuSign caps at one hour. |
| `timeoutSeconds` / `uploadTimeoutSeconds` | no | `15` / `60` | Guzzle timeouts. |

## Sending an envelope

```php
use LauLamanApps\DocumentSigner\Sdk\Document\Document;
use LauLamanApps\DocumentSigner\Sdk\Envelope\Envelope;
use LauLamanApps\DocumentSigner\Sdk\Signer\Signer;
use LauLamanApps\DocumentSigner\Sdk\Signer\SigningOrder;
use LauLamanApps\DocumentSigner\DocuSign\DocuSignProvider;

$envelope = new Envelope(
    name:         'Statement of Work',
    documents:    [
        new Document(
            id:   'sow',
            name: 'Statement of Work',
            html: <<<HTML
                <h1>Statement of Work</h1>
                <p>Customer: {[text:customer:fullname]}</p>
                <p>Customer signature: {[signature:customer:sig]}
                   on {[date:customer:signdate]}</p>
                <p>Provider signature: {[signature:provider:sig]}
                   on {[date:provider:signdate]}</p>
            HTML,
        ),
    ],
    signers:      [
        new Signer(key: 'customer', name: 'Jane Doe',  email: 'jane@example.com', order: 1),
        new Signer(key: 'provider', name: 'John Smith', email: 'john@acme.com',    order: 2),
    ],
    emailSubject: 'Please sign the Statement of Work',
    signingOrder: SigningOrder::Sequential,
);

$receipt = (new DocuSignProvider($config))->send($envelope);
echo $receipt->provider;           // "docusign" (also: DocuSignProvider::NAME)
echo $receipt->providerEnvelopeId; // DocuSign envelopeId GUID
```

## Status mapping

| DocuSign `status` | SDK `EnvelopeStatus` |
| --- | --- |
| `created` | `Draft` |
| `sent` | `Sent` |
| `delivered` | `Delivered` |
| `completed`, `signed` | `Completed` |
| `declined` | `Declined` |
| `voided` | `Voided` |
| anything else | `Unknown` |

## Endpoint mapping

| SDK call | HTTP |
| --- | --- |
| `send()` | `POST /v2.1/accounts/{accountId}/envelopes` with base64-encoded documents and anchor tabs. |
| `getStatus()` | `GET /v2.1/accounts/{accountId}/envelopes/{envelopeId}` |
| `downloadSigned()` | `GET /v2.1/accounts/{accountId}/envelopes/{envelopeId}/documents/combined` (single merged PDF). |
| `cancel()` | `PUT /v2.1/accounts/{accountId}/envelopes/{envelopeId}` with `{ "status": "voided", "voidedReason": "..." }`. |

## Field mapping

| `FieldType` | DocuSign tab bucket |
| --- | --- |
| `Signature` | `signHereTabs` |
| `Initials` | `initialHereTabs` |
| `Text` | `textTabs` (required, 180 × 18) |
| `Date` | `dateSignedTabs` (auto-populated when signed) |
| `Checkbox` | `checkboxTabs` |

Every tab uses anchor placement (`anchorString`, `anchorXOffset/YOffset = 0`,
`anchorUnits = pixels`, `anchorCaseSensitive = true`, `anchorIgnoreIfNotPresent = false`)
so DocuSign positions the field exactly where the SDK rendered the
`**DS:type:signer:name**` token.

## Sequential signing

`SigningOrder::Sequential` writes `routingOrder = Signer::$order` on each
recipient; signers receive the envelope in ascending order. Parallel signing
sets `routingOrder = 1` for everyone.

## Token caching

`DocuSignJwtAuth` caches the access token in memory until 60 seconds before
expiry. Reuse one `DocuSignProvider` (or one `DocuSignJwtAuth`) per process to
avoid hitting the oauth endpoint on every call.

If you operate in a long-lived worker, the cache is per-instance — wire it
behind a shared cache (APCu, Redis) by composing your own `DocuSignClient` and
caching the access token externally.

## Troubleshooting

- **`consent_required` from oauth/token**: a user that hasn't granted consent.
  Open the consent URL above (with the correct redirect) as that user.
- **`invalid_grant`**: clock skew or wrong RSA private key. Check `iat` / `exp`
  on the assertion are within a few minutes of UTC.
- **`USER_DOES_NOT_BELONG_TO_SPECIFIED_ACCOUNT`**: the `accountId` and `userId`
  are not from the same account. Double-check the account selector in the
  DocuSign admin UI.
- **Field placed on wrong page / not found**: confirm the anchor `**DS:...**`
  is in the PDF text layer (`pdftotext yourfile.pdf -`). If it's gone, see
  [PDF rendering](../pdf-rendering.md).
