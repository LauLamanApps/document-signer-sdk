<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Tests\Field;

use LauLamanApps\DocumentSigner\Sdk\Field\FieldType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FieldTypeTest extends TestCase
{
    #[Test]
    #[DataProvider('tokens')]
    public function it_maps_aliases_to_canonical_cases(string $token, FieldType $expected): void
    {
        self::assertSame($expected, FieldType::fromPlaceholderToken($token));
    }

    /**
     * @return iterable<string, array{string, FieldType}>
     */
    public static function tokens(): iterable
    {
        yield 'signature canonical'   => ['signature', FieldType::Signature];
        yield 'signature alias'       => ['sig', FieldType::Signature];
        yield 'initials canonical'    => ['initials', FieldType::Initials];
        yield 'initials alias'        => ['init', FieldType::Initials];
        yield 'text canonical'        => ['text', FieldType::Text];
        yield 'text alias'            => ['txt', FieldType::Text];
        yield 'date'                  => ['date', FieldType::Date];
        yield 'checkbox canonical'    => ['checkbox', FieldType::Checkbox];
        yield 'checkbox alias'        => ['check', FieldType::Checkbox];
        yield 'mixed case + padding'  => ['  Signature  ', FieldType::Signature];
    }

    #[Test]
    public function it_rejects_unknown_tokens(): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('Unknown field type: stamp');

        FieldType::fromPlaceholderToken('stamp');
    }
}
