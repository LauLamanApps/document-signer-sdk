<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Document;

use LauLamanApps\DocumentSigner\Sdk\Pdf\FooterPlacement;
use LauLamanApps\DocumentSigner\Sdk\Pdf\HeaderPlacement;

final readonly class Document
{
    /**
     * @param string               $id               Stable identifier for this document within the envelope.
     * @param string               $name             Display name (without extension); becomes the filename of the resulting PDF.
     * @param string               $html             Source HTML containing `{[type:signer:name]}` placeholders.
     * @param string|null          $headerHtml       Optional HTML rendered as the page header. Ignored when null/empty.
     * @param string|null          $footerHtml       Optional HTML rendered as the page footer. Ignored when null/empty.
     * @param HeaderPlacement      $headerPlacement  Where the header appears — every page (default) or only the first.
     * @param FooterPlacement      $footerPlacement  Where the footer appears — every page (default) or only the first.
     */
    public function __construct(
        public string          $id,
        public string          $name,
        public string          $html,
        public ?string         $headerHtml      = null,
        public ?string         $footerHtml      = null,
        public HeaderPlacement $headerPlacement = HeaderPlacement::AllPages,
        public FooterPlacement $footerPlacement = FooterPlacement::AllPages,
    ) {
        if ($id === '') {
            throw new \InvalidArgumentException('Document id must be non-empty.');
        }
        if ($name === '') {
            throw new \InvalidArgumentException('Document name must be non-empty.');
        }
        if ($html === '') {
            throw new \InvalidArgumentException('Document html must be non-empty.');
        }
        if ($headerHtml !== null && trim($headerHtml) === '') {
            throw new \InvalidArgumentException('Document headerHtml must be non-empty when provided.');
        }
        if ($footerHtml !== null && trim($footerHtml) === '') {
            throw new \InvalidArgumentException('Document footerHtml must be non-empty when provided.');
        }
    }

    public function hasHeader(): bool
    {
        return $this->headerHtml !== null && $this->headerHtml !== '';
    }

    public function hasFooter(): bool
    {
        return $this->footerHtml !== null && $this->footerHtml !== '';
    }
}
