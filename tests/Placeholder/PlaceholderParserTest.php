<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Tests\Placeholder;

use LauLamanApps\DocumentSigner\Sdk\Exception\PlaceholderException;
use LauLamanApps\DocumentSigner\Sdk\Field\FieldType;
use LauLamanApps\DocumentSigner\Sdk\Placeholder\PlaceholderParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PlaceholderParserTest extends TestCase
{
    #[Test]
    public function it_returns_empty_array_for_html_without_placeholders(): void
    {
        self::assertSame([], (new PlaceholderParser())->parse('<p>Hello world.</p>'));
    }

    #[Test]
    public function it_parses_multiple_placeholders_in_document_order(): void
    {
        $html = '<p>{[text:signer1:fullname]} signs {[signature:signer1:sig]} on {[date:signer1:signdate]}</p>'
            . '<p>{[signature:signer2:sig]}</p>';

        $parsed = (new PlaceholderParser())->parse($html);

        self::assertCount(4, $parsed);
        self::assertSame(FieldType::Text,      $parsed[0]->type);
        self::assertSame(FieldType::Signature, $parsed[1]->type);
        self::assertSame(FieldType::Date,      $parsed[2]->type);
        self::assertSame(FieldType::Signature, $parsed[3]->type);
        self::assertSame('signer2', $parsed[3]->signerKey);
        self::assertSame('sig',     $parsed[3]->fieldName);
    }

    #[Test]
    public function byte_offsets_locate_the_token_exactly(): void
    {
        $html = '<p>Hello {[signature:s1:sig]} world.</p>';
        [$placeholder] = (new PlaceholderParser())->parse($html);

        self::assertSame('{[signature:s1:sig]}', $placeholder->raw);
        self::assertSame(
            $placeholder->raw,
            substr($html, $placeholder->byteOffset, strlen($placeholder->raw)),
        );
    }

    #[Test]
    public function it_tolerates_whitespace_around_segments_and_aliases_resolve(): void
    {
        $html = '{[ sig : signer1 : sig_top ]}';
        [$placeholder] = (new PlaceholderParser())->parse($html);

        self::assertSame(FieldType::Signature, $placeholder->type);
        self::assertSame('signer1', $placeholder->signerKey);
        self::assertSame('sig_top', $placeholder->fieldName);
    }

    #[Test]
    public function it_throws_on_unknown_field_type(): void
    {
        $this->expectException(PlaceholderException::class);
        $this->expectExceptionMessage("Unknown field type 'stamp'");

        (new PlaceholderParser())->parse('Please {[stamp:signer:x]} here.');
    }

    #[Test]
    public function identity_key_combines_signer_and_field_name(): void
    {
        [$placeholder] = (new PlaceholderParser())->parse('{[signature:s1:sig]}');

        self::assertSame('s1:sig', $placeholder->identityKey());
    }

    #[Test]
    public function placeholders_are_required_by_default(): void
    {
        [$placeholder] = (new PlaceholderParser())->parse('{[text:s1:name]}');

        self::assertTrue($placeholder->required);
    }

    #[Test]
    public function question_mark_prefix_marks_a_placeholder_as_optional(): void
    {
        $html = '<p>{[?text:s1:name]} required {[signature:s1:sig]} optional {[?signature:s2:sig]}</p>';
        $parsed = (new PlaceholderParser())->parse($html);

        self::assertCount(3, $parsed);
        self::assertFalse($parsed[0]->required, 'optional text');
        self::assertTrue($parsed[1]->required,  'required signature');
        self::assertFalse($parsed[2]->required, 'optional signature');
    }

    #[Test]
    public function whitespace_between_question_mark_and_type_is_tolerated(): void
    {
        [$placeholder] = (new PlaceholderParser())->parse('{[ ? text : s1 : name ]}');

        self::assertFalse($placeholder->required);
        self::assertSame(FieldType::Text, $placeholder->type);
    }
}
