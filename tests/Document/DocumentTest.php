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

    #[Test]
    public function header_and_footer_are_null_by_default_and_report_as_absent(): void
    {
        $doc = new Document('d1', 'D', '<p>x</p>');

        self::assertNull($doc->headerHtml);
        self::assertNull($doc->footerHtml);
        self::assertFalse($doc->hasHeader());
        self::assertFalse($doc->hasFooter());
        self::assertSame(\LauLamanApps\DocumentSigner\Sdk\Pdf\HeaderPlacement::AllPages, $doc->headerPlacement);
        self::assertSame(\LauLamanApps\DocumentSigner\Sdk\Pdf\FooterPlacement::AllPages, $doc->footerPlacement);
    }

    #[Test]
    public function header_and_footer_html_are_carried_when_provided(): void
    {
        $doc = new Document(
            id: 'd1', name: 'D', html: '<p>body</p>',
            headerHtml: '<div>H</div>',
            footerHtml: '<div>F</div>',
            headerPlacement: \LauLamanApps\DocumentSigner\Sdk\Pdf\HeaderPlacement::FirstPage,
            footerPlacement: \LauLamanApps\DocumentSigner\Sdk\Pdf\FooterPlacement::FirstPage,
        );

        self::assertSame('<div>H</div>', $doc->headerHtml);
        self::assertSame('<div>F</div>', $doc->footerHtml);
        self::assertTrue($doc->hasHeader());
        self::assertTrue($doc->hasFooter());
        self::assertSame(\LauLamanApps\DocumentSigner\Sdk\Pdf\HeaderPlacement::FirstPage, $doc->headerPlacement);
        self::assertSame(\LauLamanApps\DocumentSigner\Sdk\Pdf\FooterPlacement::FirstPage, $doc->footerPlacement);
    }

    #[Test]
    public function it_rejects_whitespace_only_header_or_footer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Document('d1', 'D', '<p>x</p>', headerHtml: '   ');
    }
}
