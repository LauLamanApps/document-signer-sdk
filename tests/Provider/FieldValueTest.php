<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Tests\Provider;

use LauLamanApps\DocumentSigner\Sdk\Provider\FieldValue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FieldValueTest extends TestCase
{
    #[Test]
    public function it_is_a_plain_readonly_value_object(): void
    {
        $field = new FieldValue(
            documentId: 'nda',
            signerKey: 'counterparty',
            fieldName: 'iban',
            value: 'NL91ABNA0417164300',
        );

        self::assertSame('nda', $field->documentId);
        self::assertSame('counterparty', $field->signerKey);
        self::assertSame('iban', $field->fieldName);
        self::assertSame('NL91ABNA0417164300', $field->value);
    }

    #[Test]
    public function null_value_models_an_unfilled_optional_field(): void
    {
        $field = new FieldValue(documentId: 'd', signerKey: 's', fieldName: 'phone', value: null);
        self::assertNull($field->value);
    }
}
