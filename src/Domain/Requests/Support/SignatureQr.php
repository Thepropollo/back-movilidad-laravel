<?php

namespace Domain\Requests\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class SignatureQr
{
    /**
     * Texto corto que cabe en un QR y identifica la cuenta que aceptó.
     */
    public static function payload(int $documentId, string $slot, int $userId, string $nationalId): string
    {
        return 'SIGMOV|'.$documentId.'|'.$slot.'|'.$userId.'|'.$nationalId;
    }

    /**
     * @return list<list<bool>>
     */
    public static function matrix(string $payload): array
    {
        $qr = new QRCode(new QROptions([
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
        ]));
        $qr->addByteSegment($payload);

        /** @var list<list<bool>> $matrix */
        $matrix = $qr->getQRMatrix()->getMatrix(true);

        return $matrix;
    }
}
