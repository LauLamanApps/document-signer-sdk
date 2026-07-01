<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Tests\Support;

use LauLamanApps\DocumentSigner\Sdk\Support\TempFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TempFileTest extends TestCase
{
    /** @var list<string> */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            @unlink($path);
        }
    }

    #[Test]
    public function it_writes_bytes_and_returns_a_file_with_the_requested_extension(): void
    {
        $file = TempFile::fromBytes(bytes: 'PK-BYTES', prefix: 'test-signed-', extension: 'zip');
        $this->created[] = $file->getPathname();

        self::assertInstanceOf(\SplFileInfo::class, $file);
        self::assertSame('zip', $file->getExtension());
        self::assertSame('PK-BYTES', file_get_contents($file->getPathname()));
        self::assertStringContainsString('test-signed-', basename($file->getPathname()));
    }

    #[Test]
    public function it_supports_no_extension(): void
    {
        $file = TempFile::fromBytes(bytes: 'raw', prefix: 'test-raw-');
        $this->created[] = $file->getPathname();

        self::assertSame('', $file->getExtension());
        self::assertSame('raw', file_get_contents($file->getPathname()));
    }

    #[Test]
    public function it_tolerates_a_dot_prefixed_extension(): void
    {
        $file = TempFile::fromBytes(bytes: '{}', prefix: 'test-json-', extension: '.json');
        $this->created[] = $file->getPathname();

        self::assertSame('json', $file->getExtension());
    }

    #[Test]
    public function each_call_produces_a_distinct_file(): void
    {
        $a = TempFile::fromBytes(bytes: 'A', prefix: 'test-uniq-', extension: 'txt');
        $b = TempFile::fromBytes(bytes: 'B', prefix: 'test-uniq-', extension: 'txt');
        $this->created[] = $a->getPathname();
        $this->created[] = $b->getPathname();

        self::assertNotSame($a->getPathname(), $b->getPathname());
    }
}
