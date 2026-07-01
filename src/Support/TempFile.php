<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Support;

use LauLamanApps\DocumentSigner\Sdk\Exception\DocumentSignerException;

/**
 * Utility for materialising provider response bytes into an on-disk temp file.
 *
 * Used by {@see \LauLamanApps\DocumentSigner\Sdk\Provider\SignatureProvider::downloadAudit()}
 * implementations that need to hand callers an {@see \SplFileInfo} rather than a
 * raw byte string.
 *
 * The returned file is created under `sys_get_temp_dir()`. Callers own its
 * lifecycle — unlink it, or copy the contents to a durable location, when
 * the audit is no longer needed. Nothing here removes the file for you.
 */
final class TempFile
{
    /**
     * Write `$bytes` to a fresh temp file and return an SplFileInfo pointing at it.
     *
     * @param string $bytes     Raw file contents (JSON string, PDF bytes, ...).
     * @param string $prefix    Filename prefix, e.g. `docusign-audit-`.
     * @param string $extension File extension without leading dot, e.g. `json` or `pdf`.
     *                          Leave empty for no extension.
     */
    public static function fromBytes(string $bytes, string $prefix, string $extension = ''): \SplFileInfo
    {
        $baseDir = sys_get_temp_dir();
        $seed = tempnam($baseDir, $prefix);
        if ($seed === false) {
            throw new DocumentSignerException('Failed to allocate a temp file for provider download.');
        }

        $path = $extension === '' ? $seed : $seed . '.' . ltrim($extension, '.');
        if ($path !== $seed) {
            // Move the atomically-created placeholder so the returned path carries
            // the extension consumers use to detect content type.
            if (!@rename($seed, $path)) {
                @unlink($seed);
                throw new DocumentSignerException("Failed to rename temp file to '{$path}'.");
            }
        }

        if (file_put_contents($path, $bytes) === false) {
            @unlink($path);
            throw new DocumentSignerException("Failed to write temp file '{$path}'.");
        }

        return new \SplFileInfo($path);
    }
}
