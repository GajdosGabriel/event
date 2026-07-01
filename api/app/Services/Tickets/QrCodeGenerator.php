<?php

namespace App\Services\Tickets;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\ResultInterface;

class QrCodeGenerator
{
    /**
     * Build a PNG QR code for the given ticket check-in token.
     */
    public function forToken(string $token, int $size = 300): ResultInterface
    {
        return (new Builder(
            writer: new PngWriter(),
            data: 'TICKET:' . $token,
            size: $size,
            margin: 10,
        ))->build();
    }
}
