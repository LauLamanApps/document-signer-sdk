<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Tests\Pdf;

use LauLamanApps\DocumentSigner\Sdk\Exception\DocumentSignerException;
use LauLamanApps\DocumentSigner\Sdk\Pdf\BrowsershotPdfRenderer;
use LauLamanApps\DocumentSigner\Sdk\Pdf\PdfRenderer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BrowsershotPdfRendererTest extends TestCase
{
    #[Test]
    public function it_constructs_when_browsershot_is_installed(): void
    {
        if (!class_exists(\Spatie\Browsershot\Browsershot::class)) {
            self::markTestSkipped('spatie/browsershot is not installed; the guard-fires path is tested elsewhere.');
        }

        $renderer = new BrowsershotPdfRenderer();

        self::assertInstanceOf(PdfRenderer::class, $renderer);
    }

    #[Test]
    public function it_throws_a_helpful_error_when_browsershot_is_missing(): void
    {
        if (class_exists(\Spatie\Browsershot\Browsershot::class)) {
            self::markTestSkipped('spatie/browsershot IS installed; cannot exercise the missing-package guard.');
        }

        $this->expectException(DocumentSignerException::class);
        $this->expectExceptionMessage('composer require spatie/browsershot');

        new BrowsershotPdfRenderer();
    }
}
