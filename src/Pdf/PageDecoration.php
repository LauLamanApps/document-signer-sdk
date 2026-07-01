<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Pdf;

/**
 * Page-level decoration (header / footer HTML and their placement) passed to
 * {@see PdfRenderer::render()} alongside the body HTML.
 *
 * Constructed by provider implementations from a {@see \LauLamanApps\DocumentSigner\Sdk\Document\Document},
 * but exposed here so custom renderers and application code can hand-craft
 * decoration for one-off rendering flows too.
 */
final readonly class PageDecoration
{
    public function __construct(
        public ?string          $headerHtml       = null,
        public ?string          $footerHtml       = null,
        public HeaderPlacement  $headerPlacement  = HeaderPlacement::AllPages,
        public FooterPlacement  $footerPlacement  = FooterPlacement::AllPages,
    ) {}

    public function hasHeader(): bool
    {
        return $this->headerHtml !== null && $this->headerHtml !== '';
    }

    public function hasFooter(): bool
    {
        return $this->footerHtml !== null && $this->footerHtml !== '';
    }

    public function isEmpty(): bool
    {
        return !$this->hasHeader() && !$this->hasFooter();
    }
}
