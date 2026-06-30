<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Placeholder;

use LauLamanApps\DocumentSigner\Sdk\Field\FieldType;

final readonly class ParsedPlaceholder
{
    /**
     * @param string    $raw         Original token as it appeared in the HTML, e.g. `{[signature:signer1:sig]}`.
     * @param FieldType $type        Parsed field type.
     * @param string    $signerKey   Signer slot key (the middle segment of the placeholder).
     * @param string    $fieldName   Field name (the last segment of the placeholder).
     * @param int       $byteOffset  Byte offset of `$raw` in the source HTML.
     */
    public function __construct(
        public string    $raw,
        public FieldType $type,
        public string    $signerKey,
        public string    $fieldName,
        public int       $byteOffset,
    ) {}

    /**
     * Stable key uniquely identifying this (signer, field) pair within a document.
     */
    public function identityKey(): string
    {
        return $this->signerKey . ':' . $this->fieldName;
    }
}
