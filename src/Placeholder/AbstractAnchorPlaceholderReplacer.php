<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Placeholder;

/**
 * Shared placeholder→anchor logic.
 *
 * Walks the HTML once, replaces each placeholder token with a near-invisible inline
 * marker containing a provider-specific anchor string, and collects the anchor→field
 * mapping the provider needs to position fields after the PDF is rendered.
 *
 * Subclasses only define the anchor token format ({@see formatAnchor()}).
 */
abstract class AbstractAnchorPlaceholderReplacer implements PlaceholderReplacer
{
    public function replace(string $html, array $placeholders): PreparedDocument
    {
        if ($placeholders === []) {
            return new PreparedDocument($html, []);
        }

        // Replace from the end of the string backwards so earlier byte offsets stay valid.
        $ordered = $placeholders;
        usort($ordered, static fn (ParsedPlaceholder $a, ParsedPlaceholder $b) => $b->byteOffset <=> $a->byteOffset);

        $fields = [];
        foreach ($ordered as $placeholder) {
            $anchor = $this->formatAnchor($placeholder);
            $marker = $this->wrapAnchor($anchor);

            $html = substr_replace(
                $html,
                $marker,
                $placeholder->byteOffset,
                strlen($placeholder->raw),
            );

            $fields[] = new PreparedField(
                type: $placeholder->type,
                signerKey: $placeholder->signerKey,
                fieldName: $placeholder->fieldName,
                anchorString: $anchor,
            );
        }

        // Preserve original document order in the returned field list.
        usort($fields, static function (PreparedField $a, PreparedField $b) use ($placeholders) {
            return self::originalOffset($a, $placeholders) <=> self::originalOffset($b, $placeholders);
        });

        return new PreparedDocument($html, $fields);
    }

    /**
     * Provider-specific anchor token. Must be unique enough that the provider's
     * text extractor finds exactly one match in the rendered PDF.
     */
    abstract protected function formatAnchor(ParsedPlaceholder $placeholder): string;

    /**
     * Wrap the anchor in inline HTML so it ends up in the PDF text layer but does
     * not visibly disrupt the document. Subclasses may override to e.g. inject
     * additional styling, or to wrap the anchor in a block element.
     */
    protected function wrapAnchor(string $anchor): string
    {
        $escaped = htmlspecialchars($anchor, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '<span data-ds-anchor="1" style="color:#ffffff;font-size:1pt;line-height:0;'
            . 'letter-spacing:0;white-space:nowrap;">' . $escaped . '</span>';
    }

    /**
     * @param ParsedPlaceholder[] $placeholders
     */
    private static function originalOffset(PreparedField $field, array $placeholders): int
    {
        foreach ($placeholders as $p) {
            if ($p->signerKey === $field->signerKey && $p->fieldName === $field->fieldName) {
                return $p->byteOffset;
            }
        }
        return PHP_INT_MAX;
    }
}
