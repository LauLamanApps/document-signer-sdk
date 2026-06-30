<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Placeholder;

final readonly class PreparedDocument
{
    /**
     * @param string          $html   HTML with provider-specific anchor markup substituted in.
     * @param PreparedField[] $fields Anchor metadata for every placeholder discovered.
     */
    public function __construct(
        public string $html,
        public array  $fields,
    ) {}
}
