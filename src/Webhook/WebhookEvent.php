<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Webhook;

/**
 * Contract every provider's webhook-event enum implements.
 *
 * Providers each expose their own string-backed enum with the vendor-specific
 * event vocabulary (`PACKAGE_COMPLETE`, `envelope-completed`, `signature_request_signed`, …).
 * Extending {@see \BackedEnum} makes that explicit: every `WebhookEvent` is a
 * string-backed enum case, so `from()`/`tryFrom()`/`cases()` are part of the
 * contract, and an enum with an `Unknown` case can always resolve a token to a
 * non-null case. By having each of those enums implement this interface,
 * application code can classify a callback without knowing which vendor emitted it:
 *
 * ```php
 * function handle(WebhookEvent $event): void {
 *     match (true) {
 *         $event->isCompleted() => $this->onEnvelopeSigned(),
 *         $event->isDeclined()  => $this->onEnvelopeDeclined(),
 *         $event->isFailure()   => $this->onSigningFailed(),
 *         $event->isProgress()  => $this->onSignerProgress(),
 *         default               => null,
 *     };
 * }
 * ```
 *
 * The four `is…()` predicates are non-overlapping. An event is expected to
 * answer `true` from at most one of them; unknown-in-purpose events return
 * `false` from all four.
 */
interface WebhookEvent extends \BackedEnum
{
    /**
     * The provider-native token as returned inside the callback payload
     * (`"PACKAGE_COMPLETE"`, `"envelope-completed"`, ...).
     */
    public function value(): string;

    /**
     * True when the envelope has been fully signed by every required signer.
     * Consumers should treat this as the moment to persist the signed
     * documents and mark the workflow complete.
     */
    public function isCompleted(): bool;

    /**
     * True when a signer declined to sign or opted out of the envelope.
     * The envelope will not complete without operator intervention.
     */
    public function isDeclined(): bool;

    /**
     * True for technical failures that prevent completion but aren't a
     * deliberate decline — e.g. KBA failure, delivery bounce, signer lockout,
     * envelope expiration.
     */
    public function isFailure(): bool;

    /**
     * True for mid-flow progress events — a single signer completing, an
     * individual document being signed, the envelope reaching "ready for
     * completion" — but not the final `isCompleted()` moment.
     */
    public function isProgress(): bool;
}
