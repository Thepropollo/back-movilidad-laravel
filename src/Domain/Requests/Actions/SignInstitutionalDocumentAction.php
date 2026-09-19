<?php

namespace Domain\Requests\Actions;

use Domain\Auth\Models\User;
use Domain\Requests\Models\DocumentSignature;
use Domain\Requests\Models\GeneratedDocument;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Support\DigitalSignatureService;
use Domain\Requests\Support\RequestWorkflow;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SignInstitutionalDocumentAction
{
    public function __construct(
        protected GenerateInstitutionalDocumentAction $generateAction,
        protected DigitalSignatureService $digitalSignature
    ) {}

    public function execute(
        GeneratedDocument $document,
        User $actor,
        string $slot,
        string $password,
        ?string $ipAddress = null,
        ?string $signatureImage = null
    ): DocumentSignature {
        $slots = GeneratedDocument::signatureSlots($document->document_type);
        if (! isset($slots[$slot])) {
            throw ValidationException::withMessages(['slot' => ['Este documento no tiene ese espacio de firma.']]);
        }

        if (! $actor->hasRole($slots[$slot]['roles'])) {
            throw ValidationException::withMessages(['slot' => ['Su rol no puede firmar como '.$slots[$slot]['label'].'.']]);
        }

        if ($document->signatures()->where('slot', $slot)->exists()) {
            throw ValidationException::withMessages(['slot' => ['Ese espacio ya está firmado.']]);
        }

        if (! Hash::check($password, $actor->getAuthPassword())) {
            throw ValidationException::withMessages(['password' => ['La contraseña no coincide. Confirme su identidad para firmar.']]);
        }

        $hash = $document->file_hash;
        if (! $hash && $document->file_path && Storage::disk('local')->exists($document->file_path)) {
            $hash = hash('sha256', Storage::disk('local')->get($document->file_path));
            $document->update(['file_hash' => $hash]);
        }

        $signature = DocumentSignature::create([
            'document_id' => $document->id,
            'user_id' => $actor->id,
            'slot' => $slot,
            'slot_label' => $slots[$slot]['label'],
            'document_hash' => $hash ?: hash('sha256', (string) $document->id.now()->timestamp),
            'key_fingerprint' => $this->digitalSignature->fingerprint(),
            'ip_address' => $ipAddress,
            'signed_at' => now(),
        ]);

        if ($document->source === 'generated') {
            $this->generateAction->rebuild($document->fresh());
            $hash = $document->fresh()->file_hash ?: $hash;
            $signature->update(['document_hash' => $hash]);
        }

        $crypto = $this->digitalSignature->sign([
            'document_id' => $document->id,
            'document_type' => $document->document_type,
            'request_id' => $document->request_id,
            'slot' => $slot,
            'user_id' => $actor->id,
            'national_id' => $actor->national_id,
            'document_hash' => $hash,
            'signed_at' => optional($signature->signed_at)?->toIso8601String(),
        ]);
        $signature->update([
            'signed_payload' => $crypto['payload'],
            'crypto_signature' => $crypto['signature'],
            'key_fingerprint' => $crypto['fingerprint'],
        ]);

        if ($signatureImage) {
            $path = $this->storeSignatureImage($signature->id, $signatureImage);
            if ($path) {
                $signature->update(['signature_image_path' => $path]);
            }
        }

        if ($document->request_id) {
            $request = MobilizationRequest::find($document->request_id);
            if ($request) {
                RequestWorkflow::record(
                    $request,
                    $request->status,
                    'DOCUMENTO_FIRMADO',
                    $actor->id,
                    $document->label().' firmado digitalmente como '.$slots[$slot]['label'].'.'
                );
            }
        }

        return $signature->fresh('user');
    }

    private function storeSignatureImage(int $signatureId, string $dataUrl): ?string
    {
        if (! str_starts_with($dataUrl, 'data:image')) {
            return null;
        }
        $parts = explode(',', $dataUrl, 2);
        if (count($parts) !== 2) {
            return null;
        }
        $binary = base64_decode($parts[1], true);
        if ($binary === false || strlen($binary) < 32) {
            return null;
        }
        $path = 'firmas/firma-'.$signatureId.'.png';
        Storage::disk('local')->put($path, $binary);

        return $path;
    }
}
