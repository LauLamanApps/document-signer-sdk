<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Tests\Placeholder;

use LauLamanApps\DocumentSigner\Sdk\Field\FieldType;
use LauLamanApps\DocumentSigner\Sdk\Placeholder\AbstractAnchorPlaceholderReplacer;
use LauLamanApps\DocumentSigner\Sdk\Placeholder\ParsedPlaceholder;
use LauLamanApps\DocumentSigner\Sdk\Placeholder\PlaceholderParser;
use LauLamanApps\DocumentSigner\Sdk\Placeholder\PreparedDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AbstractAnchorPlaceholderReplacerTest extends TestCase
{
    #[Test]
    public function it_returns_input_unchanged_when_no_placeholders(): void
    {
        $replacer = $this->fakeReplacer();

        $prepared = $replacer->replace('<p>Hello</p>', []);

        self::assertInstanceOf(PreparedDocument::class, $prepared);
        self::assertSame('<p>Hello</p>', $prepared->html);
        self::assertSame([], $prepared->fields);
    }

    #[Test]
    public function it_substitutes_every_placeholder_with_an_anchor_wrapper(): void
    {
        $html = '<p>{[text:s1:fullname]} signs {[signature:s1:sig]}</p>';
        $parsed = (new PlaceholderParser())->parse($html);

        $prepared = $this->fakeReplacer()->replace($html, $parsed);

        self::assertStringNotContainsString('{[text:s1:fullname]}', $prepared->html);
        self::assertStringNotContainsString('{[signature:s1:sig]}', $prepared->html);
        self::assertStringContainsString('A:text:s1:fullname', $prepared->html);
        self::assertStringContainsString('A:signature:s1:sig', $prepared->html);
        self::assertStringContainsString('data-ds-anchor="1"', $prepared->html);
    }

    #[Test]
    public function returned_fields_preserve_original_document_order(): void
    {
        $html = '<p>{[text:s1:fullname]} {[signature:s1:sig]} {[date:s1:signdate]}</p>';
        $parsed = (new PlaceholderParser())->parse($html);

        $prepared = $this->fakeReplacer()->replace($html, $parsed);

        self::assertCount(3, $prepared->fields);
        self::assertSame(FieldType::Text,      $prepared->fields[0]->type);
        self::assertSame(FieldType::Signature, $prepared->fields[1]->type);
        self::assertSame(FieldType::Date,      $prepared->fields[2]->type);
    }

    #[Test]
    public function it_escapes_anchor_text_in_html(): void
    {
        $replacer = new class extends AbstractAnchorPlaceholderReplacer {
            protected function formatAnchor(ParsedPlaceholder $placeholder): string
            {
                return '<token>';
            }
        };
        $html = 'X {[text:s1:fullname]} Y';
        $parsed = (new PlaceholderParser())->parse($html);

        $prepared = $replacer->replace($html, $parsed);

        self::assertStringNotContainsString('<token>', $prepared->html);
        self::assertStringContainsString('&lt;token&gt;', $prepared->html);
    }

    private function fakeReplacer(): AbstractAnchorPlaceholderReplacer
    {
        return new class extends AbstractAnchorPlaceholderReplacer {
            protected function formatAnchor(ParsedPlaceholder $placeholder): string
            {
                return sprintf('A:%s:%s:%s',
                    $placeholder->type->value, $placeholder->signerKey, $placeholder->fieldName);
            }
        };
    }
}
