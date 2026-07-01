# Placeholder syntax

## The shape

```
{[type:signer:name]}     — required (default)
{[?type:signer:name]}    — optional
```

Three colon-separated segments inside double curly braces. Whitespace around
the braces and around each colon is tolerated; whitespace **inside** a segment
is not. A leading `?` before the type marks the field as optional; without it
the field defaults to required.

| Segment | Meaning | Allowed characters |
| --- | --- | --- |
| `?` (optional) | If present, marks the field as optional. Default is required. | literal `?` |
| `type` | The kind of field. See the table below. | `[A-Za-z]+` |
| `signer` | Matches `Signer::$key` on the envelope. Tells the provider whose signature/data this is. | `[A-Za-z0-9_\-]+` |
| `name` | Field name, unique per signer per document. Used as the provider tab label. | `[A-Za-z0-9_\-]+` |

The parser lives at
[`LauLamanApps\DocumentSigner\Sdk\Placeholder\PlaceholderParser`](../src/Placeholder/PlaceholderParser.php).

## Supported types

| Token | Resolved `FieldType` | ValidSign mapping | DocuSign mapping |
| --- | --- | --- | --- |
| `signature`, `sig` | `Signature` | `SIGNATURE` / `FULLNAME` | `signHereTabs` |
| `initials`, `init` | `Initials` | `SIGNATURE` / `INITIALS` | `initialHereTabs` |
| `text`, `txt` | `Text` | `INPUT` / `TEXTFIELD` | `textTabs` |
| `date` | `Date` | `INPUT` / `LABEL` (`{approval.signed}`) | `dateSignedTabs` |
| `checkbox`, `check` | `Checkbox` | `INPUT` / `CHECKBOX` | `checkboxTabs` |

The aliases (`sig`, `init`, `txt`, `check`) exist so templates read naturally;
they resolve to the same `FieldType` enum case as the canonical form.

## Examples

### A single signer

```html
<p>I, {[text:counterparty:fullname]}, agree to the terms above.</p>
<p>Signed: {[signature:counterparty:sig]} on {[date:counterparty:signdate]}</p>
<p>{[checkbox:counterparty:opt_in]} I would like to receive updates.</p>
```

The envelope must include a matching `Signer`:

```php
new Signer(key: 'counterparty', name: 'Jane Doe', email: 'jane@example.com');
```

### Multiple signers

```html
<p>Customer: {[signature:customer:sig]} on {[date:customer:signdate]}</p>
<p>Sales rep: {[signature:salesrep:sig]} on {[date:salesrep:signdate]}</p>
```

```php
new Envelope(
    /* ... */
    signers: [
        new Signer(key: 'customer', name: 'Jane Doe',  email: 'jane@example.com', order: 1),
        new Signer(key: 'salesrep', name: 'John Smith', email: 'john@acme.com',    order: 2),
    ],
    signingOrder: SigningOrder::Sequential,
);
```

With `SigningOrder::Sequential`, `Signer::$order` controls who signs first.
With `SigningOrder::Parallel` (the default), `order` is ignored and both
signers receive the envelope simultaneously.

### Initials in a long contract

```html
<p>... terms continue here ...</p>
<div class="initials-block">{[initials:customer:initials_pg2]}</div>
```

### Required vs optional fields

Every placeholder is required by default. Prefix the type with `?` to make
the field optional — the signer can submit without filling it in:

```html
<p>Full name: {[text:customer:fullname]}</p>          <!-- required -->
<p>Phone (optional): {[?text:customer:phone]}</p>     <!-- optional -->
<p>{[?checkbox:customer:opt_in]} Receive updates</p>  <!-- optional checkbox -->
<p>Co-signer: {[?signature:witness:sig]}</p>          <!-- optional signature -->
```

Provider mapping:

- **ValidSign** — required text/checkbox use the `*esl:` tag prefix; optional
  signature/initials use `?esl:`. `SigningDate` is auto-populated and ignores
  the flag.
- **DocuSign** — the `required` attribute on `textTabs` / `checkboxTabs` is
  set to `"true"` / `"false"`. Signature and initial tabs are always required
  in DocuSign and don't take the attribute.

## Rules and gotchas

1. **Signer key must exist.** If a placeholder references a signer key that
   isn't present in `Envelope::$signers`, the provider throws a
   `ProviderException` *before* hitting the network.
2. **Field name uniqueness.** Two placeholders with the same `(signer, name)`
   pair are treated as two distinct tabs by the provider, but both end up with
   the same anchor string. If you want "sign here AND here", use distinct
   names (`sig_top`, `sig_bottom`). If you genuinely want one tab placed
   multiple times, the providers will either deduplicate or place at every
   occurrence — behaviour depends on the provider.
3. **No whitespace inside segments.** `{[signature: counterparty :sig]}` is
   valid (whitespace around colons); `{[signature:counter party:sig]}` is not.
4. **HTML-safe by construction.** Placeholders are plain ASCII inside `{[ ]}`
   — they survive Browsershot's text layer cleanly and the delimiter pair is
   distinct from the `{{ }}` echo syntax used by Blade, Twig, Vue and
   Mustache, so any of those engines can generate the HTML upstream without
   escaping.

## How the SDK transforms them

For a placeholder like `{[signature:counterparty:sig]}`, the provider replaces
the token in place with:

```html
<span data-ds-anchor="1" style="color:#ffffff;font-size:1pt;line-height:0;
      letter-spacing:0;white-space:nowrap;">{{esl_sig:Signer1:Signature:size(200,50)}}</span>
```

The exact anchor token is provider-specific:

- ValidSign: `{{esl_sig:Signer1:Signature:size(200,50)}}` — [native ValidSign text-tag](https://validsign.zendesk.com/hc/nl/articles/360037747091-Text-tags-gebruiken-binnen-documenten); the SDK maps `counterparty` to the positional `Signer1` role.
- DocuSign:  `**DS:signature:counterparty:sig**` — an anchor string used by DocuSign's anchor-tab API to position the signature tab.

The `<span>` keeps the anchor in the PDF text layer (so the provider's
extraction engine can find it) while making it invisible to the signer.

If your downstream PDF pipeline strips inline `style` attributes or rewrites
text colours, override `AbstractAnchorPlaceholderReplacer::wrapAnchor()` — see
[Extending the SDK](extending.md).
