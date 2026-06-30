<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Tests\Provider;

use LauLamanApps\DocumentSigner\Sdk\Envelope\EnvelopeStatus;
use LauLamanApps\DocumentSigner\Sdk\Provider\EnvelopeReceipt;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnvelopeReceiptTest extends TestCase
{
    #[Test]
    public function it_exposes_provider_and_id(): void
    {
        $receipt = new EnvelopeReceipt(
            provider: 'validsign',
            providerEnvelopeId: 'pkg-123',
            status: EnvelopeStatus::Sent,
            signerUrls: ['s1' => 'https://signing/s1'],
            raw: ['echo' => 'response'],
        );

        self::assertSame('validsign', $receipt->provider);
        self::assertSame('pkg-123', $receipt->providerEnvelopeId);
        self::assertSame(EnvelopeStatus::Sent, $receipt->status);
        self::assertSame(['s1' => 'https://signing/s1'], $receipt->signerUrls);
        self::assertSame(['echo' => 'response'], $receipt->raw);
    }

    #[Test]
    public function it_rejects_empty_provider(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new EnvelopeReceipt(provider: '', providerEnvelopeId: 'id', status: EnvelopeStatus::Sent);
    }

    #[Test]
    public function it_rejects_empty_envelope_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new EnvelopeReceipt(provider: 'validsign', providerEnvelopeId: '', status: EnvelopeStatus::Sent);
    }
}
