<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Placeholder;

use LauLamanApps\DocumentSigner\Sdk\Field\FieldType;

/**
 * One placeholder after it has been mapped to a provider-specific anchor.
 *
 * The provider uses `$anchorString` when building its API request (e.g. DocuSign
 * anchorString tabs, or ValidSign extractAnchor.text) so the signature/field is
 * positioned where the placeholder originally sat in the HTML.
 */
final readonly class PreparedField
{
    public function __construct(
        public FieldType $type,
        public string    $signerKey,
        public string    $fieldName,
        public string    $anchorString,
    ) {}
}
