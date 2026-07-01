# Error handling

Every failure that reaches your code from a `SignatureProvider` is a subclass
of [`ProviderException`](../src/Exception/ProviderException.php). You can catch
the base type for a coarse "the provider misbehaved" branch, or narrow to a
subclass when you want to react by category.

## Exception hierarchy

```
DocumentSignerException
├── PlaceholderException          ─ HTML has an unknown/malformed placeholder
└── ProviderException             ─ base for anything the provider surfaces
    ├── ProviderValidationException      (4xx input/state problems, e.g. bad email)
    ├── ProviderAuthenticationException  (401 / 403 — credentials or consent)
    ├── ProviderNotFoundException        (404 — envelope/package id gone)
    ├── ProviderRateLimitException       (429, with `$retryAfterSeconds`)
    └── ProviderTransientException       (5xx or transport failure)
```

Every provider error carries the same structured properties:

| Property | Type | Notes |
| --- | --- | --- |
| `$httpStatus` | `?int` | `null` for transport-level errors (no response received). |
| `$providerCode` | `?string` | Provider-native code (`error.validation.invalidEmail`, `INVALID_EMAIL_ADDRESS`, `consent_required`). |
| `$providerMessage` | `?string` | Human-readable message returned by the provider, verbatim. |
| `$providerBody` | `?string` | Raw response body for debugging. |
| `$providerEnvelopeId` | `?string` | Set when a provider-side envelope already exists (see below). |
| `isRetryable(): bool` | | `true` for `RateLimit` and `Transient`; `false` otherwise. |

The exception message itself is a one-liner safe to log verbatim:

```
ValidSign POST /packages [422 error.validation.invalidEmail]: The email field must be a valid email
DocuSign POST /v2.1/accounts/…/envelopes [400 INVALID_EMAIL_ADDRESS]: The email address is invalid.
```

## Catching by category

```php
use LauLamanApps\DocumentSigner\Sdk\Exception\ProviderAuthenticationException;
use LauLamanApps\DocumentSigner\Sdk\Exception\ProviderNotFoundException;
use LauLamanApps\DocumentSigner\Sdk\Exception\ProviderRateLimitException;
use LauLamanApps\DocumentSigner\Sdk\Exception\ProviderTransientException;
use LauLamanApps\DocumentSigner\Sdk\Exception\ProviderValidationException;

try {
    $receipt = $provider->send($envelope);
} catch (ProviderValidationException $e) {
    // Input needs fixing — surface $e->providerMessage to the user.
    logger()->warning('Envelope rejected', [
        'code'    => $e->providerCode,
        'message' => $e->providerMessage,
    ]);
    throw $e;
} catch (ProviderAuthenticationException $e) {
    // Check API keys or the DocuSign consent grant. Not retryable.
    throw $e;
} catch (ProviderRateLimitException $e) {
    // Retry after $e->retryAfterSeconds (fallback to a small default when null).
    dispatch(new RetryEnvelopeJob($envelope))->delay($e->retryAfterSeconds ?? 30);
} catch (ProviderTransientException $e) {
    // 5xx or a network blip — retry with exponential backoff.
    dispatch(new RetryEnvelopeJob($envelope))->delay(backoffSeconds($attempt));
}
```

`isRetryable()` gives you the same information as an if-chain when the exact
class doesn't matter:

```php
try {
    $provider->send($envelope);
} catch (ProviderException $e) {
    if ($e->isRetryable() && $attempt < 5) {
        dispatch(new RetryEnvelopeJob($envelope))->delay($this->backoff($e, $attempt));
        return;
    }
    throw $e;
}
```

## Recovering a provider-side envelope id after failure

When an error occurs *after* the provider has already created an envelope,
the exception carries the id so you don't lose track of it:

```php
try {
    $receipt = $provider->send($envelope);
} catch (ProviderException $e) {
    if ($e->providerEnvelopeId !== null) {
        // The envelope exists at the provider even though our call didn't
        // complete cleanly. Store the id and reconcile later.
        Log::warning('Envelope created but SDK errored; recorded for reconciliation', [
            'provider_envelope_id' => $e->providerEnvelopeId,
            'code'                 => $e->providerCode,
        ]);
        PendingReconciliation::create([
            'provider'            => 'validsign',
            'provider_envelope_id'=> $e->providerEnvelopeId,
        ]);
    }
    throw $e;
}
```

An id may be populated when:

- The provider's error body echoed the id (e.g. ValidSign returns `packageId`
  on some post-creation validation failures, DocuSign returns `envelopeId` on
  409 "already sent" responses).
- The SDK successfully created the envelope but then a subsequent step (e.g.
  building the `EnvelopeReceipt`) failed — the id is threaded through
  automatically.

Note: for transport timeouts *during* envelope creation the id may still be
unknown; you'll need to correlate via envelope metadata or a subsequent
`list envelopes` call (provider-specific).

## Retry strategy quick reference

| Exception | Retry? | Suggested delay |
| --- | --- | --- |
| `ProviderValidationException` | No | — (fix input) |
| `ProviderAuthenticationException` | No | — (fix credentials / consent) |
| `ProviderNotFoundException` | No | — |
| `ProviderRateLimitException` | Yes | `$e->retryAfterSeconds ?? 30` |
| `ProviderTransientException` | Yes | Exponential backoff, cap at 5–10 attempts |
| Any `ProviderException` where `isRetryable()` is `true` | Yes | Same as above |

## Errors from the SDK itself

- `PlaceholderException` — the HTML template contains a token whose type isn't
  one of `signature|initials|text|date|checkbox`. This is a programmer error;
  fix the template.
- `DocumentSignerException` — parent of everything the SDK throws, including
  Browsershot / PDF rendering failures.
- `\InvalidArgumentException` — thrown by value-object constructors
  (`Envelope`, `Signer`, `Document`, `EnvelopeReceipt`) when input violates a
  structural invariant. Again, a programmer error; catch at boundaries only if
  you're validating third-party input.
