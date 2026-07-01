<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Tests\Pdf;

use LauLamanApps\DocumentSigner\Sdk\Pdf\FooterPlacement;
use LauLamanApps\DocumentSigner\Sdk\Pdf\HeaderPlacement;
use LauLamanApps\DocumentSigner\Sdk\Pdf\PageDecoration;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PageDecorationTest extends TestCase
{
    #[Test]
    public function empty_decoration_reports_no_header_and_no_footer(): void
    {
        $d = new PageDecoration();

        self::assertFalse($d->hasHeader());
        self::assertFalse($d->hasFooter());
        self::assertTrue($d->isEmpty());
        self::assertSame(HeaderPlacement::AllPages, $d->headerPlacement);
        self::assertSame(FooterPlacement::AllPages, $d->footerPlacement);
    }

    #[Test]
    public function null_and_empty_string_html_are_both_treated_as_absent(): void
    {
        self::assertFalse((new PageDecoration(headerHtml: null))->hasHeader());
        self::assertFalse((new PageDecoration(headerHtml: ''))->hasHeader());
        self::assertFalse((new PageDecoration(footerHtml: null))->hasFooter());
        self::assertFalse((new PageDecoration(footerHtml: ''))->hasFooter());
    }

    #[Test]
    public function it_carries_header_and_footer_when_populated(): void
    {
        $d = new PageDecoration(
            headerHtml: '<header>H</header>',
            footerHtml: '<footer>F</footer>',
            headerPlacement: HeaderPlacement::FirstPage,
            footerPlacement: FooterPlacement::FirstPage,
        );

        self::assertTrue($d->hasHeader());
        self::assertTrue($d->hasFooter());
        self::assertFalse($d->isEmpty());
        self::assertSame(HeaderPlacement::FirstPage, $d->headerPlacement);
        self::assertSame(FooterPlacement::FirstPage, $d->footerPlacement);
    }
}
