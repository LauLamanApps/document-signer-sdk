<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Pdf;

use LauLamanApps\DocumentSigner\Sdk\Exception\DocumentSignerException;
use Spatie\Browsershot\Browsershot;

final class BrowsershotPdfRenderer implements PdfRenderer
{
    /**
     * @param \Closure(Browsershot):void|null $configure Optional hook to customise paper size,
     *                                                   margins, headers/footers, node binary, etc.
     *
     * @throws DocumentSignerException When spatie/browsershot isn't installed.
     */
    public function __construct(
        private readonly ?\Closure $configure = null,
    ) {
        if (!class_exists(Browsershot::class)) {
            throw new DocumentSignerException(
                'BrowsershotPdfRenderer requires spatie/browsershot, which is not installed. '
                . 'Install it with: composer require spatie/browsershot'
            );
        }
    }

    public function render(string $html, ?PageDecoration $decoration = null): string
    {
        $body = $this->applyInlineDecoration($html, $decoration);

        try {
            $browsershot = Browsershot::html($body)
                ->format('A4')
                ->margins(20, 15, 20, 15)
                ->showBackground()
                ->emulateMedia('print');

            $this->applyNativeDecoration($browsershot, $decoration);

            if ($this->configure !== null) {
                ($this->configure)($browsershot);
            }

            return $browsershot->pdf();
        } catch (\Throwable $e) {
            throw new DocumentSignerException(
                'Browsershot failed to render HTML to PDF: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * For {@see HeaderPlacement::FirstPage} / {@see FooterPlacement::FirstPage} we inject the
     * decoration HTML at the start / end of the body, since Chromium has no first-page-only
     * flag for its native header/footer template.
     */
    private function applyInlineDecoration(string $html, ?PageDecoration $decoration): string
    {
        if ($decoration === null || $decoration->isEmpty()) {
            return $html;
        }

        $body = $html;

        if ($decoration->hasHeader() && $decoration->headerPlacement === HeaderPlacement::FirstPage) {
            $body = $decoration->headerHtml . $body;
        }

        if ($decoration->hasFooter() && $decoration->footerPlacement === FooterPlacement::FirstPage) {
            $body .= $decoration->footerHtml;
        }

        return $body;
    }

    /**
     * For {@see HeaderPlacement::AllPages} / {@see FooterPlacement::AllPages} we hand the
     * decoration to Browsershot's native header/footer template, which repeats it on every
     * printed page.
     */
    private function applyNativeDecoration(Browsershot $browsershot, ?PageDecoration $decoration): void
    {
        if ($decoration === null) {
            return;
        }

        $showChrome = false;

        if ($decoration->hasHeader() && $decoration->headerPlacement === HeaderPlacement::AllPages) {
            $browsershot->headerHtml((string) $decoration->headerHtml);
            $showChrome = true;
        }

        if ($decoration->hasFooter() && $decoration->footerPlacement === FooterPlacement::AllPages) {
            $browsershot->footerHtml((string) $decoration->footerHtml);
            $showChrome = true;
        }

        if ($showChrome) {
            $browsershot->showBrowserHeaderAndFooter();
        }
    }
}
