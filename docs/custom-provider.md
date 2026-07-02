# Writing a custom provider

This guide walks through implementing a brand-new e-signature provider against
the SDK contract — for example a Hellosign / Dropbox Sign or Adobe Sign
implementation, or an internal in-house signing service.

If you only need to tweak the PDF renderer or change the anchor format for an
existing provider, see [Extending the SDK](extending.md). This guide covers
the third (and largest) seam: a full `SignatureProvider` implementation.

## What a provider does

For every envelope your provider receives it should:

1. For every `Document` in the envelope:
   - Parse the HTML for `{[type:signer:name]}` placeholders.
   - Hand them to a provider-specific `PlaceholderReplacer` that swaps each
     placeholder for an anchor token the provider's API can find later.
   - Render the resulting HTML to a PDF via the SDK's `PdfRenderer`.
2. Translate every `PreparedField` into a tab / field entry the provider's API
   understands, grouped by signer.
3. Build the provider-specific request: signers, email subject/blurb, the
   PDFs (typically base64 or multipart), and the field metadata.
4. Send it and return an `EnvelopeReceipt` that names the provider and carries
   the provider-assigned envelope id.
5. Translate the provider's status vocabulary into the normalised
   `EnvelopeStatus` enum on the way back.

You implement the `SignatureProvider` interface — `send`, `getStatus`,
`downloadSigned`, `downloadSignedDocument`, `downloadAudit`, `getFieldValues`,
`cancel` — and translate provider errors into `ProviderException`.

## 1. Pick a name and create the package

If you're packaging the provider for reuse, follow the same shape as the
built-in implementations:

```
your-package/
  composer.json
  src/
    HellosignProvider.php
    HellosignConfig.php
    Placeholder/HellosignPlaceholderReplacer.php
    Http/HellosignClient.php
  tests/
  phpunit.xml.dist
```

Composer dependencies are at minimum `php: ^8.5`,
`laulamanapps/document-signer-sdk: *`, and an HTTP client of your choice.

By convention, expose a `public const string NAME = 'hellosign';` on the
provider class — the manager and consumer code will read it.

## 2. Build the placeholder replacer

Subclass `AbstractAnchorPlaceholderReplacer` and pick an anchor token format
that is:

- **Unique** in normal contract text (so the provider's text extraction
  doesn't accidentally match a real sentence).
- **Stable** through your PDF renderer (no characters that the renderer will
  drop, escape, or split across pages).
- **Parseable** if you ever want to reverse-map an anchor string back to a
  field (handy for logging).

```php
use LauLamanApps\DocumentSigner\Sdk\Placeholder\AbstractAnchorPlaceholderReplacer;
use LauLamanApps\DocumentSigner\Sdk\Placeholder\ParsedPlaceholder;

final class HellosignPlaceholderReplacer extends AbstractAnchorPlaceholderReplacer
{
    protected function formatAnchor(ParsedPlaceholder $placeholder): string
    {
        // Hellosign uses [...] anchor tags with a configurable prefix.
        return sprintf(
            '[hs|%s|%s|%s]',
            $placeholder->type->value,
            $placeholder->signerKey,
            $placeholder->fieldName,
        );
    }
}
```

The base class handles byte-offset substitution and the invisible-text
`<span>` wrapper. If your renderer mangles the default `<span>`, override
`wrapAnchor(string $anchor): string`.

## 3. Build the config DTO

A `final readonly` DTO that validates its inputs in the constructor:

```php
final readonly class HellosignConfig
{
    public function __construct(
        public string $apiKey,
        public string $baseUrl = 'https://api.hellosign.com/v3',
        public int    $timeoutSeconds = 15,
        public int    $uploadTimeoutSeconds = 60,
    ) {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('Hellosign API key must be non-empty.');
        }
        if (!preg_match('#^https?://#i', $baseUrl)) {
            throw new \InvalidArgumentException("Hellosign baseUrl must be http(s), got: '{$baseUrl}'");
        }
    }
}
```

## 4. Build a thin HTTP client

Wrap your HTTP transport (Guzzle, Symfony HttpClient, …) in a class that
takes an injectable `ClientInterface` so tests can swap it out:

```php
use LauLamanApps\DocumentSigner\Sdk\Exception\ProviderException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

final class HellosignClient
{
    private ClientInterface $http;

    public function __construct(
        private readonly HellosignConfig $config,
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client([
            'base_uri' => $this->config->baseUrl . '/',
            'timeout'  => $this->config->timeoutSeconds,
            'auth'     => [$this->config->apiKey, ''],
        ]);
    }

    /** @return array<string, mixed> */
    public function createSignatureRequest(array $payload, array $files): array
    {
        // POST to /signature_request/send_with_template or equivalent,
        // wrapping any RequestException in a ProviderException carrying
        // the HTTP status and raw body.
        // ...
    }

    // getRequest($id), downloadFiles($id), cancelRequest($id) similarly.
}
```

Wrap every transport-level exception into `ProviderException`:

```php
} catch (RequestException $e) {
    $response = $e->getResponse();
    throw new ProviderException(
        "Hellosign {$method} {$path} failed: " . $e->getMessage(),
        httpStatus: $response?->getStatusCode(),
        providerBody: $response?->getBody()?->getContents(),
        previous: $e,
    );
}
```

## 5. Implement the provider

```php
use LauLamanApps\DocumentSigner\Sdk\Envelope\Envelope;
use LauLamanApps\DocumentSigner\Sdk\Envelope\EnvelopeStatus;
use LauLamanApps\DocumentSigner\Sdk\Pdf\BrowsershotPdfRenderer;
use LauLamanApps\DocumentSigner\Sdk\Pdf\PdfRenderer;
use LauLamanApps\DocumentSigner\Sdk\Placeholder\PlaceholderParser;
use LauLamanApps\DocumentSigner\Sdk\Provider\EnvelopeReceipt;
use LauLamanApps\DocumentSigner\Sdk\Provider\SignatureProvider;

final class HellosignProvider implements SignatureProvider
{
    public const string NAME = 'hellosign';

    private readonly HellosignConfig $config;
    private readonly HellosignClient $client;
    private readonly PdfRenderer $pdfRenderer;
    private readonly HellosignPlaceholderReplacer $replacer;
    private readonly PlaceholderParser $parser;

    public function __construct(
        HellosignConfig $config,
        ?HellosignClient $client = null,
        ?PdfRenderer $pdfRenderer = null,
        ?HellosignPlaceholderReplacer $replacer = null,
        ?PlaceholderParser $parser = null,
    ) {
        $this->config      = $config;
        $this->client      = $client      ?? new HellosignClient($config);
        $this->pdfRenderer = $pdfRenderer ?? new BrowsershotPdfRenderer();
        $this->replacer    = $replacer    ?? new HellosignPlaceholderReplacer();
        $this->parser      = $parser      ?? new PlaceholderParser();
    }

    public function send(Envelope $envelope): EnvelopeReceipt
    {
        $files = [];
        $tabs  = [];

        foreach ($envelope->documents as $document) {
            $parsed   = $this->parser->parse($document->html);
            $prepared = $this->replacer->replace($document->html, $parsed);

            $this->assertFieldsResolvable($envelope, $document, $prepared->fields);

            $files[] = [
                'name'     => $document->name . '.pdf',
                'contents' => $this->pdfRenderer->render($prepared->html),
            ];

            foreach ($prepared->fields as $field) {
                $tabs[$field->signerKey][] = $this->mapField($field, $document->id);
            }
        }

        $payload = $this->buildPayload($envelope, $tabs);
        $response = $this->client->createSignatureRequest($payload, $files);

        $providerId = $response['signature_request']['signature_request_id'] ?? null;
        if (!is_string($providerId) || $providerId === '') {
            throw new ProviderException(
                'Hellosign did not return a signature_request_id.',
                providerBody: json_encode($response),
            );
        }

        return new EnvelopeReceipt(
            provider: self::NAME,
            providerEnvelopeId: $providerId,
            status: EnvelopeStatus::Sent,
            signerUrls: $this->extractSignerUrls($response),
            raw: $response,
        );
    }

    public function getStatus(string $providerEnvelopeId): EnvelopeStatus
    {
        $response = $this->client->getRequest($providerEnvelopeId);
        return $this->mapStatus($response['signature_request']['is_complete'] ?? null,
                                $response['signature_request']['is_declined']  ?? null);
    }

    public function downloadSigned(string $providerEnvelopeId): \SplFileInfo
    {
        return TempFile::fromBytes(
            bytes: $this->client->downloadSignedArchive($providerEnvelopeId),
            prefix: 'hellosign-signed-',
            extension: 'zip',
        );
    }

    public function downloadSignedDocument(string $providerEnvelopeId, string $documentId): \SplFileInfo
    {
        return TempFile::fromBytes(
            bytes: $this->client->downloadSignedDocument($providerEnvelopeId, $documentId),
            prefix: 'hellosign-signed-doc-',
            extension: 'pdf',
        );
    }

    public function downloadAudit(string $providerEnvelopeId): \SplFileInfo
    {
        return TempFile::fromBytes(
            bytes: $this->client->downloadEvidence($providerEnvelopeId),
            prefix: 'hellosign-audit-',
            extension: 'pdf',
        );
    }

    public function cancel(string $providerEnvelopeId, ?string $reason = null): void
    {
        $this->client->cancelRequest($providerEnvelopeId);
    }

    // mapField / buildPayload / extractSignerUrls / mapStatus / assertFieldsResolvable...
}
```

### Method contracts

| Method | Must do | Must not do |
| --- | --- | --- |
| `send` | Push the envelope to "sent" state at the provider. Return the provider's id and a non-`Draft` status. | Throw any exception other than `ProviderException` (wrap renderer/HTTP errors). |
| `getStatus` | Return one of the `EnvelopeStatus` enum cases. Use `EnvelopeStatus::Unknown` for vocabulary you don't recognise. | Cache the result. Callers expect a fresh fetch. |
| `downloadSigned` | Return an `\SplFileInfo` pointing at a temp file holding a ZIP archive of the signed documents — one PDF per envelope document. Use `Sdk\Support\TempFile::fromBytes()` with extension `zip`. | Return a merged single PDF (loses per-doc boundaries). Delete the file — the caller owns its lifecycle. |
| `downloadSignedDocument` | Return an `\SplFileInfo` pointing at a temp file holding the signed PDF for a single document — keyed by the same `$documentId` the caller passed on `Document::$id`. Use `Sdk\Support\TempFile::fromBytes()` with extension `pdf`. | Fall back to `downloadSigned()` + ZIP extraction on the caller's behalf — providers all expose a single-document endpoint. Delete the file. |
| `downloadAudit` | Return an `\SplFileInfo` pointing at a temp file holding the provider's audit / evidence data. Use `Sdk\Support\TempFile::fromBytes()` and set the extension (`.json` / `.pdf`) so callers can dispatch on content type. | Keep the file open. Delete the file — the caller owns its lifecycle. |
| `cancel` | Void / archive / delete according to the provider's semantics. Idempotent if possible. | Swallow errors. Surface them as `ProviderException`. |

### Status mapping pattern

Translate every status string in a `match`. Always end with an `Unknown`
default rather than throwing:

```php
private function mapStatus(?bool $isComplete, ?bool $isDeclined): EnvelopeStatus
{
    if ($isDeclined === true) return EnvelopeStatus::Declined;
    if ($isComplete === true) return EnvelopeStatus::Completed;
    return EnvelopeStatus::Sent;
}
```

Provider vocabularies drift; an unrecognised status should never crash a
caller's polling loop.

### Validating that placeholders match signers

Re-use the same precondition the built-in providers apply:

```php
foreach ($prepared->fields as $field) {
    if (!$envelope->signerByKey($field->signerKey) instanceof Signer) {
        throw new ProviderException(sprintf(
            "Document '%s' references unknown signer key '%s' in field '%s'.",
            $document->id, $field->signerKey, $field->fieldName,
        ));
    }
}
```

This fails fast — before any HTTP call — when the template references a
signer the envelope doesn't declare.

## 6. Wire it into Laravel (optional)

If you're using `laulamanapps/document-signer-laravel`, register the driver
in any service provider's `boot()`:

```php
use LauLamanApps\DocumentSigner\Laravel\Facades\DocumentSigner;

DocumentSigner::extend(HellosignProvider::NAME, function ($app, array $config): HellosignProvider {
    return new HellosignProvider(new HellosignConfig(
        apiKey: $config['api_key'],
    ));
});
```

Then add a `hellosign` block under `document-signer.drivers` in your
`config/document-signer.php` (publish it first if you haven't):

```php
'drivers' => [
    'hellosign' => [
        'api_key' => env('HELLOSIGN_API_KEY'),
    ],
],
```

And `DocumentSigner::driver('hellosign')->send($envelope)` works.

## 7. Test it

The built-in providers ship with end-to-end tests that exercise the full
parse → replace → render → upload pipeline against a mocked HTTP client.
Mirror the pattern:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use LauLamanApps\DocumentSigner\Sdk\Pdf\PdfRenderer;

// Mock the HTTP transport:
$mock = new MockHandler([new Response(200, [], json_encode([
    'signature_request' => ['signature_request_id' => 'sr-123'],
]))]);
$history = new \ArrayObject();
$stack = HandlerStack::create($mock);
$stack->push(Middleware::history($history));
$http = new Client(['handler' => $stack]);

// Fake the renderer so tests don't launch Chromium:
$fakeRenderer = new class implements PdfRenderer {
    public function render(string $html): string { return "%PDF-FAKE\n" . $html; }
};

$provider = new HellosignProvider(
    config:      new HellosignConfig(apiKey: 'k'),
    client:      new HellosignClient(new HellosignConfig(apiKey: 'k'), $http),
    pdfRenderer: $fakeRenderer,
);

$receipt = $provider->send($envelope);

self::assertSame('hellosign', $receipt->provider);
self::assertSame('sr-123',    $receipt->providerEnvelopeId);
self::assertCount(1, $history);

// Inspect $history[0]['request'] to assert on the payload that hit the wire.
```

`ArrayObject` (not a plain `[]`) is important — Guzzle's history middleware
keeps a reference to the container, and a plain array won't survive being
returned from a test helper.

## Reference implementations to study

When you get stuck, the built-in providers are short enough to read end to
end:

- ValidSign: [`src/ValidSignProvider.php`](https://github.com/LauLamanApps/document-signer-validsign/blob/main/src/ValidSignProvider.php),
  [`src/Placeholder/ValidSignPlaceholderReplacer.php`](https://github.com/LauLamanApps/document-signer-validsign/blob/main/src/Placeholder/ValidSignPlaceholderReplacer.php),
  [`tests/ValidSignProviderTest.php`](https://github.com/LauLamanApps/document-signer-validsign/blob/main/tests/ValidSignProviderTest.php).
- DocuSign: [`src/DocuSignProvider.php`](https://github.com/LauLamanApps/document-signer-docusign/blob/main/src/DocuSignProvider.php),
  [`src/Placeholder/DocuSignPlaceholderReplacer.php`](https://github.com/LauLamanApps/document-signer-docusign/blob/main/src/Placeholder/DocuSignPlaceholderReplacer.php),
  [`tests/DocuSignProviderTest.php`](https://github.com/LauLamanApps/document-signer-docusign/blob/main/tests/DocuSignProviderTest.php).

Both are under 300 lines.

## Checklist before publishing

- [ ] `SignatureProvider` interface implemented, all four methods.
- [ ] `PlaceholderReplacer` (subclass of `AbstractAnchorPlaceholderReplacer`)
  with a distinctive `formatAnchor()` token.
- [ ] `public const string NAME` on the provider class.
- [ ] `Config` DTO validates everything it can in the constructor.
- [ ] HTTP client takes an injectable `ClientInterface`.
- [ ] All provider errors wrapped in `ProviderException` with HTTP status
  and raw body.
- [ ] Status mapping uses `match` with an `EnvelopeStatus::Unknown` default.
- [ ] Receipt carries `provider: self::NAME` and the provider's id.
- [ ] Tests with `MockHandler` + a fake `PdfRenderer` cover `send`,
  `getStatus`, and the unknown-signer precondition.
- [ ] (Laravel) `DocumentSignerManager::extend()` registration documented
  in the package's README.
