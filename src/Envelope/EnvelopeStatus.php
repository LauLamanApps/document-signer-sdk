<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Envelope;

enum EnvelopeStatus: string
{
    case Draft     = 'draft';
    case Sent      = 'sent';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Declined  = 'declined';
    case Voided    = 'voided';
    case Expired   = 'expired';
    case Unknown   = 'unknown';
}
