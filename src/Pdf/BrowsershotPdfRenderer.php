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
     */
    public function __construct(
        private readonly ?\Closure $configure = null,
    ) {}

    public function render(string $html): string
    {
        try {
            $browsershot = Browsershot::html($html)
                ->format('A4')
                ->margins(20, 15, 20, 15)
                ->showBackground()
                ->emulateMedia('print');

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
}
