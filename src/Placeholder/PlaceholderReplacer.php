<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Placeholder;

/**
 * Provider-specific strategy that swaps the SDK's `{[type:signer:name]}` placeholders
 * for anchor markup the provider understands (DocuSign anchorString, ValidSign extractAnchor, ...).
 */
interface PlaceholderReplacer
{
    /**
     * @param ParsedPlaceholder[] $placeholders Result of {@see PlaceholderParser::parse()}.
     */
    public function replace(string $html, array $placeholders): PreparedDocument;
}
