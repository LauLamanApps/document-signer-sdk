<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Envelope;

use LauLamanApps\DocumentSigner\Sdk\Document\Document;
use LauLamanApps\DocumentSigner\Sdk\Signer\Signer;
use LauLamanApps\DocumentSigner\Sdk\Signer\SigningOrder;

final readonly class Envelope
{
    /**
     * @param string         $name          Envelope title, shown to signers.
     * @param Document[]     $documents     One or more documents bundled in this envelope.
     * @param Signer[]       $signers       One or more signers; each is referenced by `key` in placeholders.
     * @param string         $emailSubject  Subject line used by the provider when notifying signers.
     * @param string|null    $emailMessage  Optional body text included in the notification email.
     * @param SigningOrder   $signingOrder  Whether signers sign in parallel or in sequence (by `Signer::$order`).
     * @param \DateTimeImmutable|null $expiresAt Optional expiration timestamp.
     * @param array<string, scalar|null> $metadata Provider-passthrough metadata (custom fields).
     */
    public function __construct(
        public string             $name,
        public array              $documents,
        public array              $signers,
        public string             $emailSubject,
        public ?string            $emailMessage = null,
        public SigningOrder       $signingOrder = SigningOrder::Parallel,
        public ?\DateTimeImmutable $expiresAt = null,
        public array              $metadata = [],
    ) {
        if ($name === '') {
            throw new \InvalidArgumentException('Envelope name must be non-empty.');
        }
        if ($emailSubject === '') {
            throw new \InvalidArgumentException('Envelope email subject must be non-empty.');
        }
        if ($documents === []) {
            throw new \InvalidArgumentException('Envelope must contain at least one document.');
        }
        if ($signers === []) {
            throw new \InvalidArgumentException('Envelope must contain at least one signer.');
        }

        $seenKeys = [];
        foreach ($signers as $signer) {
            if (!$signer instanceof Signer) {
                throw new \InvalidArgumentException('Envelope signers must be instances of Signer.');
            }
            if (isset($seenKeys[$signer->key])) {
                throw new \InvalidArgumentException("Duplicate signer key in envelope: '{$signer->key}'.");
            }
            $seenKeys[$signer->key] = true;
        }

        $seenDocIds = [];
        foreach ($documents as $document) {
            if (!$document instanceof Document) {
                throw new \InvalidArgumentException('Envelope documents must be instances of Document.');
            }
            if (isset($seenDocIds[$document->id])) {
                throw new \InvalidArgumentException("Duplicate document id in envelope: '{$document->id}'.");
            }
            $seenDocIds[$document->id] = true;
        }
    }

    public function signerByKey(string $key): ?Signer
    {
        foreach ($this->signers as $signer) {
            if ($signer->key === $key) {
                return $signer;
            }
        }
        return null;
    }

    /**
     * Start a fluent builder — {@see EnvelopeBuilder} — as an alternative to the
     * positional constructor for envelopes assembled step-by-step.
     */
    public static function builder(): EnvelopeBuilder
    {
        return new EnvelopeBuilder();
    }
}
