# Webhook events

The SDK doesn't ship a webhook receiver itself — that's the Laravel package's
job — but it does define a shared contract every provider's event enum
implements, so consumers can classify a callback without knowing which vendor
emitted it.

## The contract

```php
namespace LauLamanApps\DocumentSigner\Sdk\Webhook;

interface WebhookEvent
{
    public function value(): string;      // provider-native token, e.g. "PACKAGE_COMPLETE"
    public function provider(): string;   // matches SignatureProvider::NAME

    public function isCompleted(): bool;  // envelope fully signed
    public function isDeclined(): bool;   // signer declined / opted out
    public function isFailure(): bool;    // KBA failure, delivery bounce, lock-out, expiry
    public function isProgress(): bool;   // mid-flow (individual signer/document event)
}
```

The four `is…()` predicates are **non-overlapping**. Any event returns `true`
from at most one of them; events with no dispatch meaning (envelope
creation, template creation, archive/restore admin actions) return `false`
from all four.

## Provider implementations

- **ValidSign** — [`LauLamanApps\DocumentSigner\ValidSign\Webhook\EventType`](../../valid-sign/src/Webhook/EventType.php),
  20 cases covering the full ValidSign callback vocabulary.
- DocuSign — not yet implemented; the interface makes it straightforward to
  add later.

Each provider's enum ships a matching `tryFromPayload(array): ?self` static
helper that pulls the event token from the decoded callback body.

## Handling events polymorphically

```php
use LauLamanApps\DocumentSigner\Sdk\Webhook\WebhookEvent;
use LauLamanApps\DocumentSigner\ValidSign\Webhook\EventType as ValidSignEvent;

function handleEnvelopeEvent(?WebhookEvent $event, array $payload): void
{
    match (true) {
        $event === null       => Log::warning('Unknown webhook event', ['payload' => $payload]),
        $event->isCompleted() => $this->markContractSigned($payload),
        $event->isDeclined()  => $this->notifyDeclined($payload),
        $event->isFailure()   => $this->pageOncall($payload),
        $event->isProgress()  => $this->recordSignerProgress($payload),
        default               => null, // administrative event we don't act on
    };
}

// Later, from a Laravel event listener:
$event = ValidSignEvent::tryFromPayload($laravelEvent->payload);
$this->handleEnvelopeEvent($event, $laravelEvent->payload);
```

The `WebhookEvent` interface lets the classification handler stay
provider-agnostic — once DocuSign's enum is added, the same
`handleEnvelopeEvent()` signature accepts it without changes.
