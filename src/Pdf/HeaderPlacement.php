<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Pdf;

/**
 * Where a document's header HTML should appear in the rendered PDF.
 *
 *  - {@see AllPages}  — the header is rendered at the top of every page via the
 *    renderer's native header slot (e.g. Chromium's `headerTemplate`).
 *  - {@see FirstPage} — the header is injected as a regular block at the top
 *    of the body HTML, so it lives on page 1 only. The native header slot is
 *    left empty. This is the pragmatic escape hatch: no browser exposes a
 *    reliable "first-page only" flag for the printed-page header, so we
 *    substitute inline content for the same visual effect.
 */
enum HeaderPlacement: string
{
    case AllPages  = 'all_pages';
    case FirstPage = 'first_page';
}
