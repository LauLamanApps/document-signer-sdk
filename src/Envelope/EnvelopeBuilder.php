<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Envelope;

use LauLamanApps\DocumentSigner\Sdk\Document\Document;
use LauLamanApps\DocumentSigner\Sdk\Pdf\FooterPlacement;
use LauLamanApps\DocumentSigner\Sdk\Pdf\HeaderPlacement;
use LauLamanApps\DocumentSigner\Sdk\Signer\Signer;
use LauLamanApps\DocumentSigner\Sdk\Signer\SigningOrder;

/**
 * Fluent builder for {@see Envelope}. Constructed via {@see Envelope::builder()}
 * or {@see self::create()}; every mutator returns `$this` for chaining, and
 * {@see build()} produces the immutable envelope after validation.
 *
 * The builder itself performs no validation as you go — invariants are checked
 * inside `Envelope`'s constructor when you call `build()`, so a half-constructed
 * builder never blows up mid-chain.
 */
final class EnvelopeBuilder
{
    private ?string $name = null;
    private ?string $emailSubject = null;
    private ?string $emailMessage = null;
    private ?\DateTimeImmutable $expiresAt = null;
    private SigningOrder $signingOrder = SigningOrder::Parallel;

    /** @var Document[] */
    private array $documents = [];

    /** @var Signer[] */
    private array $signers = [];

    /** @var array<string, scalar|null> */
    private array $metadata = [];

    public static function create(): self
    {
        return new self();
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function emailSubject(string $subject): self
    {
        $this->emailSubject = $subject;
        return $this;
    }

    public function emailMessage(?string $message): self
    {
        $this->emailMessage = $message;
        return $this;
    }

    /**
     * Add a document. Accepts either a pre-built {@see Document} in the first
     * positional argument, or the constructor arguments as named parameters:
     *
     * ```php
     * ->addDocument(new Document(id: 'd1', name: 'D', html: '<p>x</p>'))
     * // OR
     * ->addDocument(id: 'd1', name: 'D', html: '<p>x</p>')
     * ```
     *
     * Header/footer HTML and placement work the same way when using the named-args form.
     */
    public function addDocument(
        ?Document $document = null,
        ?string $id = null,
        ?string $name = null,
        ?string $html = null,
        ?string $headerHtml = null,
        ?string $footerHtml = null,
        HeaderPlacement $headerPlacement = HeaderPlacement::AllPages,
        FooterPlacement $footerPlacement = FooterPlacement::AllPages,
    ): self {
        if ($document !== null) {
            if ($id !== null || $name !== null || $html !== null || $headerHtml !== null || $footerHtml !== null) {
                throw new \InvalidArgumentException(
                    'addDocument() takes either a Document instance OR the constructor named args, not both.'
                );
            }
            $this->documents[] = $document;
            return $this;
        }

        if ($id === null || $name === null || $html === null) {
            throw new \InvalidArgumentException(
                'addDocument() requires either a Document instance or (id, name, html) as named arguments.'
            );
        }

        $this->documents[] = new Document(
            id: $id,
            name: $name,
            html: $html,
            headerHtml: $headerHtml,
            footerHtml: $footerHtml,
            headerPlacement: $headerPlacement,
            footerPlacement: $footerPlacement,
        );

        return $this;
    }

    /**
     * Add a signer. Accepts either a pre-built {@see Signer} in the first
     * positional argument, or the constructor arguments as named parameters:
     *
     * ```php
     * ->addSigner(new Signer(key: 'k', name: 'n', email: 'e@e.e'))
     * // OR
     * ->addSigner(key: 'k', name: 'n', email: 'e@e.e')
     * ```
     */
    public function addSigner(
        ?Signer $signer = null,
        ?string $key = null,
        ?string $name = null,
        ?string $email = null,
        int $order = 1,
        ?string $language = null,
    ): self {
        if ($signer !== null) {
            if ($key !== null || $name !== null || $email !== null || $language !== null) {
                throw new \InvalidArgumentException(
                    'addSigner() takes either a Signer instance OR the constructor named args, not both.'
                );
            }
            $this->signers[] = $signer;
            return $this;
        }

        if ($key === null || $name === null || $email === null) {
            throw new \InvalidArgumentException(
                'addSigner() requires either a Signer instance or (key, name, email) as named arguments.'
            );
        }

        $this->signers[] = new Signer(
            key: $key,
            name: $name,
            email: $email,
            order: $order,
            language: $language,
        );

        return $this;
    }

    public function signingOrder(SigningOrder $order): self
    {
        $this->signingOrder = $order;
        return $this;
    }

    public function expiresAt(?\DateTimeImmutable $at): self
    {
        $this->expiresAt = $at;
        return $this;
    }

    /**
     * Replace the entire metadata map. Use {@see withMetadata()} for per-key edits.
     *
     * @param array<string, scalar|null> $metadata
     */
    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function withMetadata(string $key, string|int|float|bool|null $value): self
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * @throws \LogicException When required fields (`name`, `emailSubject`) are missing.
     */
    public function build(): Envelope
    {
        if ($this->name === null) {
            throw new \LogicException('EnvelopeBuilder::name() is required before build().');
        }
        if ($this->emailSubject === null) {
            throw new \LogicException('EnvelopeBuilder::emailSubject() is required before build().');
        }

        return new Envelope(
            name:         $this->name,
            documents:    $this->documents,
            signers:      $this->signers,
            emailSubject: $this->emailSubject,
            emailMessage: $this->emailMessage,
            signingOrder: $this->signingOrder,
            expiresAt:    $this->expiresAt,
            metadata:     $this->metadata,
        );
    }
}
