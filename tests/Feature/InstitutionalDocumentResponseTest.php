<?php

namespace Tests\Feature;

use App\Http\Controllers\Request\InstitutionalDocumentController;
use Domain\Auth\Models\User;
use Domain\Requests\Models\GeneratedDocument;
use ReflectionMethod;
use Tests\TestCase;

class InstitutionalDocumentResponseTest extends TestCase
{
    public function test_document_response_omits_storage_path_and_private_signature_data(): void
    {
        $author = new User;
        $author->setAttribute('id', 5);
        $author->setAttribute('first_name', 'Ada');
        $author->setAttribute('last_name', 'Vera');
        $author->setAttribute('email', 'ada@example.test');
        $author->setAttribute('national_id', '0000000000');

        $document = new GeneratedDocument;
        $document->setAttribute('id', 42);
        $document->setAttribute('document_type', 'custom');
        $document->setAttribute('file_path', 'private/secret.pdf');
        $document->setAttribute('original_filename', 'document.pdf');
        $document->setAttribute('file_hash', str_repeat('a', 64));
        $document->setRelation('author', $author);
        $document->setRelation('signatures', collect());

        $controller = app(InstitutionalDocumentController::class);
        $present = new ReflectionMethod($controller, 'present');
        $payload = $present->invoke($controller, $document, new User);

        $this->assertArrayNotHasKey('file_path', $payload);
        $this->assertArrayNotHasKey('signatures', $payload);
        $this->assertArrayNotHasKey('email', $payload['author']);
        $this->assertArrayNotHasKey('national_id', $payload['author']);
        $this->assertSame(42, $payload['id']);
    }
}
