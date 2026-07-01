<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Pdf;

interface PdfRenderer
{
    /**
     * Render the given HTML document to a single PDF and return its raw binary contents.
     *
     * Implementations MUST preserve every character of the HTML text layer so anchor-text
     * placeholders remain findable by downstream signature providers.
     *
     * `$decoration` optionally carries per-document header and footer HTML plus placement
     * enums. Implementations MUST honour {@see HeaderPlacement::AllPages} /
     * {@see FooterPlacement::AllPages} by wiring the underlying engine's native header/footer
     * template, and MUST honour {@see HeaderPlacement::FirstPage} /
     * {@see FooterPlacement::FirstPage} by injecting the HTML inline at the start/end of the
     * body (since browsers don't expose a reliable "first-page only" flag for their native
     * header/footer slots).
     */
    public function render(string $html, ?PageDecoration $decoration = null): string;
}
