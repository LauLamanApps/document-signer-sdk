<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Field;

enum FieldType: string
{
    case Signature = 'signature';
    case Initials  = 'initials';
    case Text      = 'text';
    case Date      = 'date';
    case Checkbox  = 'checkbox';

    public static function fromPlaceholderToken(string $token): self
    {
        return match (strtolower(trim($token))) {
            'signature', 'sig' => self::Signature,
            'initials', 'init' => self::Initials,
            'text', 'txt'      => self::Text,
            'date'             => self::Date,
            'checkbox', 'check' => self::Checkbox,
            default => throw new \ValueError("Unknown field type: {$token}"),
        };
    }
}
