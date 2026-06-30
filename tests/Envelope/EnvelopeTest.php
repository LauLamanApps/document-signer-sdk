<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Tests\Envelope;

use LauLamanApps\DocumentSigner\Sdk\Document\Document;
use LauLamanApps\DocumentSigner\Sdk\Envelope\Envelope;
use LauLamanApps\DocumentSigner\Sdk\Signer\Signer;
use LauLamanApps\DocumentSigner\Sdk\Signer\SigningOrder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnvelopeTest extends TestCase
{
    #[Test]
    public function it_constructs_with_valid_values(): void
    {
        $envelope = new Envelope(
            name:         'env',
            documents:    [new Document('d1', 'Doc', '<p>x</p>')],
            signers:      [new Signer('s1', 'Jane', 'jane@example.com')],
            emailSubject: 'Please sign',
            signingOrder: SigningOrder::Sequential,
        );

        self::assertSame('env', $envelope->name);
        self::assertCount(1, $envelope->documents);
        self::assertCount(1, $envelope->signers);
        self::assertSame(SigningOrder::Sequential, $envelope->signingOrder);
    }

    #[Test]
    public function it_resolves_signer_by_key(): void
    {
        $jane = new Signer('s1', 'Jane', 'jane@example.com');
        $envelope = new Envelope(
            name:         'env',
            documents:    [new Document('d1', 'D', '<p>x</p>')],
            signers:      [$jane],
            emailSubject: 'subj',
        );

        self::assertSame($jane, $envelope->signerByKey('s1'));
        self::assertNull($envelope->signerByKey('missing'));
    }

    #[Test]
    public function it_rejects_duplicate_signer_keys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Duplicate signer key");

        new Envelope(
            name:         'env',
            documents:    [new Document('d1', 'D', '<p>x</p>')],
            signers:      [
                new Signer('s1', 'Jane', 'jane@example.com'),
                new Signer('s1', 'John', 'john@example.com'),
            ],
            emailSubject: 'subj',
        );
    }

    #[Test]
    public function it_rejects_duplicate_document_ids(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Duplicate document id");

        new Envelope(
            name:         'env',
            documents:    [
                new Document('d1', 'A', '<p>x</p>'),
                new Document('d1', 'B', '<p>y</p>'),
            ],
            signers:      [new Signer('s1', 'Jane', 'jane@example.com')],
            emailSubject: 'subj',
        );
    }

    #[Test]
    public function it_requires_at_least_one_document(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Envelope('env', [], [new Signer('s1', 'Jane', 'jane@example.com')], 'subj');
    }

    #[Test]
    public function it_requires_at_least_one_signer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Envelope('env', [new Document('d1', 'D', '<p>x</p>')], [], 'subj');
    }
}
