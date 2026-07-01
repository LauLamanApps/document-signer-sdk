# PDF rendering

The SDK turns HTML into PDF through a single contract:

```php
namespace LauLamanApps\DocumentSigner\Sdk\Pdf;

interface PdfRenderer
{
    public function render(string $html, ?PageDecoration $decoration = null): string;
}
```

Anything that produces a valid PDF whose text layer preserves the anchor
strings will work. The second parameter carries optional per-document header
and footer HTML — see [Page decoration](#page-decoration) below.

## Page decoration

Each `Document` can carry a header and/or footer HTML fragment plus placement
enums controlling where they appear:

```php
use LauLamanApps\DocumentSigner\Sdk\Document\Document;
use LauLamanApps\DocumentSigner\Sdk\Pdf\HeaderPlacement;
use LauLamanApps\DocumentSigner\Sdk\Pdf\FooterPlacement;

new Document(
    id:   'nda',
    name: 'NDA',
    html: '<h1>Mutual NDA</h1>…',
    headerHtml: '<div style="font-size:9pt;text-align:center;">Acme Legal</div>',
    footerHtml: '<div style="font-size:8pt;text-align:right;">Confidential</div>',
    headerPlacement: HeaderPlacement::FirstPage,   // AllPages | FirstPage
    footerPlacement: FooterPlacement::AllPages,    // AllPages | FirstPage
);
```

The provider hands the resulting `PageDecoration` to the renderer. Renderers
implement the two placement modes differently:

| Placement | How it's rendered |
| --- | --- |
| `AllPages` | The underlying engine's native repeat-on-every-page header/footer template (Browsershot / Puppeteer `headerTemplate` / `footerTemplate`). |
| `FirstPage` | Injected as a regular block at the start or end of the body HTML. Browsers don't expose a "first-page only" flag for the native header template, so this is the pragmatic escape hatch. |

**Caveat for `FirstPage` footers**: the injected footer sits at the *end of
the body content*. If the body doesn't fill the page, the footer will appear
directly after the content rather than at the bottom of page 1. If you need
strict bottom-of-page-1 placement, either pad the body content or use CSS
`position: absolute; bottom: 0;` inside your footer HTML.

## Default: Browsershot

`BrowsershotPdfRenderer` wraps [`spatie/browsershot`](https://github.com/spatie/browsershot),
which drives headless Chromium via Puppeteer. Defaults:

- A4 paper
- 20mm top/bottom, 15mm left/right margins
- `print` media emulation
- CSS backgrounds rendered (`showBackground()`)

These defaults work well for typical contract templates and reliably keep the
hidden anchor `<span>`s in the text layer.

### Install spatie/browsershot

The SDK ships the `BrowsershotPdfRenderer` class but not the Composer
dependency — Browsershot is listed under `suggest`, so callers only pull it
in when they actually intend to use it. Instantiating the renderer without it
throws a `DocumentSignerException` pointing at the install command:

```bash
composer require spatie/browsershot
```

If you plan to swap to a different engine (Gotenberg, wkhtmltopdf, an
external service, spatie/laravel-pdf on Laravel), you can skip this and
implement `PdfRenderer` yourself — see the sections below.

### Install Puppeteer

Browsershot needs a Node toolchain reachable on `PATH`:

```bash
npm install puppeteer
```

If Chromium didn't download alongside Puppeteer:

```bash
node node_modules/puppeteer/install.mjs
```

### Customising the renderer

Pass a `\Closure(Browsershot)` to tweak any Browsershot setting without
subclassing:

```php
use LauLamanApps\DocumentSigner\Sdk\Pdf\BrowsershotPdfRenderer;

$renderer = new BrowsershotPdfRenderer(
    configure: function (\Spatie\Browsershot\Browsershot $b): void {
        $b->format('Letter')
          ->margins(15, 12, 15, 12)
          ->showBrowserHeaderAndFooter()
          ->headerHtml('<div style="font-size:8pt;width:100%;text-align:center;">{{ envelope }}</div>')
          ->setNodeBinary('/usr/local/bin/node')
          ->setNpmBinary('/usr/local/bin/npm');
    },
);

$provider = new ValidSignProvider(
    config: $config,
    pdfRenderer: $renderer,
);
```

The closure runs after the default configuration but before `pdf()` is called,
so anything you set in the closure wins.

### Errors

If Browsershot fails — Chromium binary missing, page navigation failed, etc. —
the renderer wraps the underlying exception in a `DocumentSignerException` so
your `send()` call always throws a documented SDK exception type.

## Writing a custom renderer

Implement `PdfRenderer` and pass it in via the provider's constructor:

```php
use LauLamanApps\DocumentSigner\Sdk\Exception\DocumentSignerException;
use LauLamanApps\DocumentSigner\Sdk\Pdf\PdfRenderer;

final class GotenbergPdfRenderer implements PdfRenderer
{
    public function __construct(
        private readonly \GuzzleHttp\ClientInterface $http,
        private readonly string $endpoint = 'http://gotenberg:3000/forms/chromium/convert/html',
    ) {}

    public function render(string $html): string
    {
        try {
            $response = $this->http->request('POST', $this->endpoint, [
                'multipart' => [
                    ['name' => 'files', 'filename' => 'index.html', 'contents' => $html],
                ],
            ]);
        } catch (\Throwable $e) {
            throw new DocumentSignerException('Gotenberg render failed: ' . $e->getMessage(), previous: $e);
        }

        return (string) $response->getBody();
    }
}
```

```php
$provider = new DocuSignProvider(
    config:      $dsConfig,
    pdfRenderer: new GotenbergPdfRenderer($guzzle),
);
```

## Text-layer preservation

Two things to verify when swapping the renderer:

1. **Anchor strings appear verbatim in the output PDF's text layer.** Run
   `pdftotext signed.pdf -` and confirm you see `{{esl_…}}` (ValidSign) or `**DS:...**` (DocuSign)
   tokens. If they don't appear, the provider can't position the field.
2. **The anchor `<span>` doesn't paginate awkwardly.** Browsershot's defaults
   are safe; if you produce paginated PDFs through another tool, ensure the
   `<span>` content doesn't get word-broken across pages.

If your pipeline rewrites the markup before rendering, override
`AbstractAnchorPlaceholderReplacer::wrapAnchor()` to emit a form your pipeline
preserves — see [Extending the SDK](extending.md).
