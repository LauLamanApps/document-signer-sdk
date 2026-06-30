<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Document;

final readonly class Document
{
    /**
     * @param string $id      Stable identifier for this document within the envelope.
     * @param string $name    Display name (without extension); becomes the filename of the resulting PDF.
     * @param string $html    Source HTML containing `{[type:signer:name]}` placeholders.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $html,
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
    }
}
