<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Tests\Signer;

use LauLamanApps\DocumentSigner\Sdk\Signer\Signer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class SignerTest extends TestCase
{
    #[Test]
    public function it_accepts_a_valid_signer(): void
    {
        $signer = new Signer(key: 'signer1', name: 'Jane Doe', email: 'jane@example.com', order: 2, language: 'nl');

        self::assertSame('signer1', $signer->key);
        self::assertSame('Jane Doe', $signer->name);
        self::assertSame('jane@example.com', $signer->email);
        self::assertSame(2, $signer->order);
        self::assertSame('nl', $signer->language);
    }

    #[Test]
    #[TestWith([''])]
    #[TestWith(['has space'])]
    public function it_rejects_keys_that_are_empty_or_contain_whitespace(string $key): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Signer(key: $key, name: 'x', email: 'a@b.c');
    }

    #[Test]
    public function it_rejects_invalid_email(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Signer(key: 'k', name: 'x', email: 'not-an-email');
    }

    #[Test]
    public function it_rejects_order_below_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Signer(key: 'k', name: 'x', email: 'a@b.c', order: 0);
    }
}
