<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Provider;

/**
 * One filled-in field value returned by {@see SignatureProvider::getFieldValues()}
 * after an envelope has been (partially or fully) signed.
 *
 * The `$fieldName` corresponds to the last segment of the SDK placeholder
 * (e.g. `iban` for `{[text:customer:iban]}`), so callers can extract signed
 * form data by name without provider-specific parsing.
 *
 * `$value` is `null` when the field was optional and left blank, or when the
 * signer hasn't reached that field yet.
 */
final readonly class FieldValue
{
    public function __construct(
        /**
         * Provider-side document identifier the field belongs to.
         *
         *  - ValidSign: the document id (matches the SDK's `Document::$id`).
         *  - DocuSign: the numeric document id assigned during envelope creation (`"1"`, `"2"`, …).
         */
        public string  $documentId,

        /**
         * Provider-native signer/role identifier who filled the field.
         *
         *  - ValidSign: the role id used in the API payload (typically the SDK signer key).
         *  - DocuSign: the string recipient id (`"1"`, `"2"`, …).
         */
        public string  $signerKey,

        /**
         * Field name from the original placeholder — `iban` for `{[text:customer:iban]}`.
         */
        public string  $fieldName,

        /**
         * The value the signer typed / selected. `null` when the field is optional
         * and empty, or when the envelope isn't yet at a stage where this field
         * has been touched.
         */
        public ?string $value,
    ) {}
}
