<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Signer;

final readonly class Signer
{
    /**
     * @param string $key      Stable identifier matching the signer slot in placeholders (`{[type:KEY:name]}`).
     * @param string $name     Display name of the signer.
     * @param string $email    Email address used by the provider to notify the signer.
     * @param int    $order    1-based routing order; ignored when SigningOrder is Parallel.
     * @param string|null $language Optional locale hint (e.g. "nl", "en") passed to the provider when supported.
     */
    public function __construct(
        public string  $key,
        public string  $name,
        public string  $email,
        public int     $order = 1,
        public ?string $language = null,
    ) {
        if ($key === '' || preg_match('/\s/', $key) === 1) {
            throw new \InvalidArgumentException("Signer key must be a non-empty whitespace-free string, got: '{$key}'");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Signer email is not valid: '{$email}'");
        }
        if ($order < 1) {
            throw new \InvalidArgumentException("Signer order must be >= 1, got: {$order}");
        }
    }
}
