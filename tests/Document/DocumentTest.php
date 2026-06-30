<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Tests\Document;

use LauLamanApps\DocumentSigner\Sdk\Document\Document;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocumentTest extends TestCase
{
    #[Test]
    public function it_constructs_with_valid_values(): void
    {
        $document = new Document(id: 'd1', name: 'Contract', html: '<p>hi</p>');

        self::assertSame('d1', $document->id);
        self::assertSame('Contract', $document->name);
        self::assertSame('<p>hi</p>', $document->html);
    }

    #[Test]
    public function it_rejects_empty_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Document(id: '', name: 'x', html: '<p>hi</p>');
    }

    #[Test]
    public function it_rejects_empty_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Document(id: 'd1', name: '', html: '<p>hi</p>');
    }

    #[Test]
    public function it_rejects_empty_html(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Document(id: 'd1', name: 'x', html: '');
    }
}
