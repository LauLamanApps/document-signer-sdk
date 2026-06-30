<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Exception;

class ProviderException extends DocumentSignerException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $providerCode = null,
        public readonly ?string $providerBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
