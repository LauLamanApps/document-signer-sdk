# Extending the SDK

Three seams are designed to be customised: the PDF renderer, the placeholder
replacer, and the signature provider itself.

## 1. Custom PDF renderer

Implement [`PdfRenderer`](../src/Pdf/PdfRenderer.php) and pass it into the
provider constructor:

```php
use LauLamanApps\DocumentSigner\Sdk\Pdf\PdfRenderer;

final class WkhtmltopdfRenderer implements PdfRenderer
{
    public function render(string $html): string
    {
        $tmpIn  = tempnam(sys_get_temp_dir(), 'html_');
        $tmpOut = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($tmpIn, $html);

        $cmd = sprintf('wkhtmltopdf --quiet %s %s', escapeshellarg($tmpIn), escapeshellarg($tmpOut));
        exec($cmd, $_, $code);
        $pdf = $code === 0 ? file_get_contents($tmpOut) : '';
        @unlink($tmpIn);
        @unlink($tmpOut);

        if ($pdf === '' || $pdf === false) {
            throw new \LauLamanApps\DocumentSigner\Sdk\Exception\DocumentSignerException('wkhtmltopdf failed.');
        }
        return $pdf;
    }
}

$provider = new DocuSignProvider($config, pdfRenderer: new WkhtmltopdfRenderer());
```

See [PDF rendering](pdf-rendering.md) for the full story including the
Browsershot defaults.

## 2. Custom placeholder replacer

If a provider needs a different anchor format, or your PDF pipeline mangles the
default `<span>` wrapper, subclass
[`AbstractAnchorPlaceholderReplacer`](../src/Placeholder/AbstractAnchorPlaceholderReplacer.php).

```php
use LauLamanApps\DocumentSigner\Sdk\Placeholder\AbstractAnchorPlaceholderReplacer;
use LauLamanApps\DocumentSigner\Sdk\Placeholder\ParsedPlaceholder;

final class HellosignReplacer extends AbstractAnchorPlaceholderReplacer
{
    protected function formatAnchor(ParsedPlaceholder $placeholder): string
    {
        // Hellosign / Dropbox Sign uses [sig|signer|name] anchor syntax.
        return sprintf(
            '[%s|%s|%s]',
            $placeholder->type->value,
            $placeholder->signerKey,
            $placeholder->fieldName,
        );
    }

    // Optional: replace the default white-text <span> with whatever your renderer preserves.
    protected function wrapAnchor(string $anchor): string
    {
        return '<font color="#ffffff" size="1">' . htmlspecialchars($anchor, ENT_QUOTES | ENT_HTML5) . '</font>';
    }
}
```

The base class handles offset bookkeeping, original-order preservation, and
emitting the `PreparedField` list. You never need to touch byte-level string
manipulation yourself.

## 3. Custom signature provider

Writing a full provider — adding Hellosign, Adobe Sign, or an in-house service
— has its own dedicated walkthrough: [Writing a custom provider](custom-provider.md).
It covers the project layout, every method's contract, status mapping,
testing pattern, and the optional Laravel-manager registration.

## Testing strategy

- **Parser / replacer**: pure-PHP, no external deps. Write straight unit tests
  against `PlaceholderParser` and your `AbstractAnchorPlaceholderReplacer`
  subclass.
- **Provider**: inject a mock Guzzle `ClientInterface` into your `*Client` and
  assert on the requests the SDK fires. Both `ValidSignClient` and
  `DocuSignClient` already accept an injected client; mirror that pattern in
  your own provider.
- **PDF renderer**: write a fake `PdfRenderer` that returns the HTML wrapped in
  a known marker, so you can assert on what the provider passes downstream
  without actually running Chromium in tests.

## Composition example

A test for the DocuSign provider end-to-end without hitting the network:

```php
use LauLamanApps\DocumentSigner\DocuSign\DocuSignConfig;
use LauLamanApps\DocumentSigner\DocuSign\DocuSignProvider;
use LauLamanApps\DocumentSigner\DocuSign\Http\DocuSignClient;
use LauLamanApps\DocumentSigner\Sdk\Pdf\PdfRenderer;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

$config = new DocuSignConfig(
    integrationKey: 'k', userId: 'u', accountId: 'a',
    privateKey: file_get_contents(__DIR__ . '/fixtures/key.pem'),
);

$mock = new MockHandler([
    new Response(200, [], json_encode(['envelopeId' => 'env-123', 'status' => 'sent'])),
]);
$httpClient = new Client(['handler' => HandlerStack::create($mock)]);

$auth = new \LauLamanApps\DocumentSigner\DocuSign\Auth\DocuSignJwtAuth($config, $httpClient);
$dsClient = new DocuSignClient($config, $auth, $httpClient);

$fakeRenderer = new class implements PdfRenderer {
    public function render(string $html): string { return "%PDF-FAKE\n" . $html; }
};

$provider = new DocuSignProvider(
    config:      $config,
    client:      $dsClient,
    pdfRenderer: $fakeRenderer,
);

$receipt = $provider->send($envelope);
assert($receipt->providerEnvelopeId === 'env-123');
```
