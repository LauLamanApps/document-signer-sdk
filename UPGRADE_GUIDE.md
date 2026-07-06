# Upgrade guide

## 1.x → 2.0

The 2.0 release changes the `WebhookEvent` contract. If you never implemented
or consumed `WebhookEvent` directly, no changes are required.

### `WebhookEvent` now extends `\BackedEnum`

```php
// before
interface WebhookEvent { /* ... */ }

// after
interface WebhookEvent extends \BackedEnum { /* ... */ }
```

Every `WebhookEvent` must now be a **string-backed enum**. A plain class that
implemented `WebhookEvent` will no longer compile — PHP forbids a non-enum
class from implementing `\BackedEnum`.

`from()`, `tryFrom()` and `cases()` are now part of the contract, and the
intended pattern for "unmatched token" is an `Unknown` enum case (see the
ValidSign/DocuSign providers) rather than returning `null` from your resolver.

**Migrate:** convert any custom `WebhookEvent` implementation to a
string-backed enum:

```php
// before
final class MyEvent implements WebhookEvent { /* ... */ }

// after
enum MyEvent: string implements WebhookEvent
{
    case Completed = 'completed';
    case Unknown   = '__UNKNOWN__';
    // value() + isCompleted()/isDeclined()/isFailure()/isProgress()
}
```

### `WebhookEvent::provider()` removed

```php
// removed from the interface
public function provider(): string;
```

The event no longer reports which provider emitted it. Provider identity now
travels alongside the event on the consuming side:

- In the Laravel package, read `DocumentSignerWebhookReceived::$provider` (a
  provider class-string). See that package's upgrade guide.
- In a bespoke integration, the provider is known at the call site that
  resolves the event, so pass it through your own plumbing.

**Migrate:** delete the `provider()` method from your enum, and drop any
`$event->provider()` calls in favour of the provider value your integration
already has in context.

### No other public API changed

`value()` and the four `is…()` predicates are unchanged.
