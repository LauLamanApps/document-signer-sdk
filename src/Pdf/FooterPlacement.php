<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Pdf;

/**
 * Where a document's footer HTML should appear in the rendered PDF.
 *
 *  - {@see AllPages}  — the footer is rendered at the bottom of every page via
 *    the renderer's native footer slot (e.g. Chromium's `footerTemplate`).
 *  - {@see FirstPage} — the footer is injected as a regular block at the end
 *    of the body HTML. Its vertical position depends on the length of the
 *    body content: with a full page of body it sits at the bottom of page 1,
 *    with less content it sits directly after the body. Callers who need
 *    strict bottom-of-page-1 placement should pad the body content or use
 *    CSS `position: absolute; bottom: 0;` inside their footer HTML.
 */
enum FooterPlacement: string
{
    case AllPages  = 'all_pages';
    case FirstPage = 'first_page';
}
