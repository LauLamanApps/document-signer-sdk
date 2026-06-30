<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Signer;

enum SigningOrder: string
{
    case Parallel   = 'parallel';
    case Sequential = 'sequential';
}
