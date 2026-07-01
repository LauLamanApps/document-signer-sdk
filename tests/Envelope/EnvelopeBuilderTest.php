<?php

declare(strict_types=1);

namespace LauLamanApps\DocumentSigner\Sdk\Tests\Envelope;

use LauLamanApps\DocumentSigner\Sdk\Document\Document;
use LauLamanApps\DocumentSigner\Sdk\Envelope\Envelope;
use LauLamanApps\DocumentSigner\Sdk\Envelope\EnvelopeBuilder;
use LauLamanApps\DocumentSigner\Sdk\Signer\Signer;
use LauLamanApps\DocumentSigner\Sdk\Signer\SigningOrder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnvelopeBuilderTest extends TestCase
{
    #[Test]
    public function it_builds_a_valid_envelope_via_the_fluent_api(): void
    {
        $doc = new Document('d1', 'D', '<p>x</p>');
        $signer = new Signer('s1', 'Jane', 'jane@example.com');

        $envelope = Envelope::builder()
            ->name('NDA')
            ->emailSubject('Please sign the NDA')
            ->emailMessage('Hi Jane')
            ->addDocument($doc)
            ->addSigner($signer)
            ->signingOrder(SigningOrder::Sequential)
            ->withMetadata('contract_id', 42)
            ->build();

        self::assertSame('NDA', $envelope->name);
        self::assertSame('Please sign the NDA', $envelope->emailSubject);
        self::assertSame('Hi Jane', $envelope->emailMessage);
        self::assertSame([$doc], $envelope->documents);
        self::assertSame([$signer], $envelope->signers);
        self::assertSame(SigningOrder::Sequential, $envelope->signingOrder);
        self::assertSame(['contract_id' => 42], $envelope->metadata);
    }

    #[Test]
    public function the_static_factory_matches_the_facade_on_envelope(): void
    {
        self::assertInstanceOf(EnvelopeBuilder::class, Envelope::builder());
        self::assertInstanceOf(EnvelopeBuilder::class, EnvelopeBuilder::create());
    }

    #[Test]
    public function multiple_documents_and_signers_accumulate_in_order(): void
    {
        $d1 = new Document('d1', 'D1', '<p>1</p>');
        $d2 = new Document('d2', 'D2', '<p>2</p>');
        $s1 = new Signer('s1', 'Jane', 'jane@example.com');
        $s2 = new Signer('s2', 'John', 'john@example.com', order: 2);

        $envelope = Envelope::builder()
            ->name('E')
            ->emailSubject('sub')
            ->addDocument($d1)
            ->addDocument($d2)
            ->addSigner($s1)
            ->addSigner($s2)
            ->build();

        self::assertSame([$d1, $d2], $envelope->documents);
        self::assertSame([$s1, $s2], $envelope->signers);
    }

    #[Test]
    public function build_throws_when_name_is_missing(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('name()');

        Envelope::builder()
            ->emailSubject('subj')
            ->addDocument(new Document('d', 'D', '<p>x</p>'))
            ->addSigner(new Signer('s', 'x', 'a@b.c'))
            ->build();
    }

    #[Test]
    public function build_throws_when_subject_is_missing(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('emailSubject()');

        Envelope::builder()
            ->name('E')
            ->addDocument(new Document('d', 'D', '<p>x</p>'))
            ->addSigner(new Signer('s', 'x', 'a@b.c'))
            ->build();
    }

    #[Test]
    public function build_delegates_domain_validation_to_the_envelope_constructor(): void
    {
        // Two signers with the same key — Envelope should reject.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate signer key');

        Envelope::builder()
            ->name('E')
            ->emailSubject('subj')
            ->addDocument(new Document('d', 'D', '<p>x</p>'))
            ->addSigner(new Signer('same', 'Jane', 'jane@example.com'))
            ->addSigner(new Signer('same', 'John', 'john@example.com'))
            ->build();
    }

    #[Test]
    public function add_signer_accepts_named_constructor_arguments(): void
    {
        $envelope = Envelope::builder()
            ->name('E')
            ->emailSubject('subj')
            ->addDocument(new Document('d', 'D', '<p>x</p>'))
            ->addSigner(key: 'k', name: 'Jane Doe', email: 'jane@example.com', order: 3, language: 'nl')
            ->build();

        self::assertCount(1, $envelope->signers);
        $s = $envelope->signers[0];
        self::assertSame('k', $s->key);
        self::assertSame('Jane Doe', $s->name);
        self::assertSame('jane@example.com', $s->email);
        self::assertSame(3, $s->order);
        self::assertSame('nl', $s->language);
    }

    #[Test]
    public function add_signer_rejects_mixing_a_signer_instance_with_named_args(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('takes either a Signer instance OR the constructor named args');

        Envelope::builder()->addSigner(
            new Signer('k', 'n', 'e@e.e'),
            name: 'shouldNotBeSet',
        );
    }

    #[Test]
    public function add_signer_rejects_missing_required_named_args(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires either a Signer instance or (key, name, email)');

        Envelope::builder()->addSigner(key: 'k');
    }

    #[Test]
    public function add_document_accepts_named_constructor_arguments(): void
    {
        $envelope = Envelope::builder()
            ->name('E')
            ->emailSubject('subj')
            ->addSigner(new Signer('s', 'Jane', 'jane@example.com'))
            ->addDocument(
                id: 'd1',
                name: 'D',
                html: '<p>body</p>',
                headerHtml: '<h>H</h>',
                headerPlacement: \LauLamanApps\DocumentSigner\Sdk\Pdf\HeaderPlacement::FirstPage,
            )
            ->build();

        self::assertCount(1, $envelope->documents);
        $d = $envelope->documents[0];
        self::assertSame('d1', $d->id);
        self::assertSame('<p>body</p>', $d->html);
        self::assertSame('<h>H</h>', $d->headerHtml);
        self::assertSame(\LauLamanApps\DocumentSigner\Sdk\Pdf\HeaderPlacement::FirstPage, $d->headerPlacement);
    }

    #[Test]
    public function add_document_rejects_mixing_an_instance_with_named_args(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('takes either a Document instance OR the constructor named args');

        Envelope::builder()->addDocument(
            new Document('d', 'D', '<p>x</p>'),
            html: '<p>other</p>',
        );
    }

    #[Test]
    public function add_document_rejects_missing_required_named_args(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires either a Document instance or (id, name, html)');

        Envelope::builder()->addDocument(id: 'd1');
    }
}
