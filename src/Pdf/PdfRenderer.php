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
     */
    public function render(string $html): string;
}
