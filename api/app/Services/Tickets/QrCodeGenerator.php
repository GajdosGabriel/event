<?php

namespace App\Services\Tickets;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\Result\GdResult;
use Endroid\QrCode\Writer\Result\ResultInterface;
use GdImage;

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

    /**
     * QR kód s adresou — telefón ho naskenuje a rovno otvorí stránku.
     *
     * Na rozdiel od `forToken()` sa vracia priamo `GdImage`, aby sa dal vložiť
     * do generovanej snímky bez zbytočného zakódovania a rozkódovania PNG.
     *
     * Prečo predvolene `margin: 0`: pri `RoundBlockSizeMode::Margin` je výstup
     * presne `size` pixelov, takže QR sa **nikdy neresampluje** a moduly
     * zostanú ostré. Tichú zónu (4 moduly, teda zhruba 14 % veľkosti QR) potom
     * musí dodať volajúci ako vnútorné odsadenie podkladu — tak to robí biela
     * karta na snímke. Kto kreslí QR samostatne, si o ňu musí povedať cez
     * `$margin`; bez nej časť čítačiek kód nenájde.
     *
     * Úroveň korekcie je stredná zámerne: vyššia znamená viac a menších
     * modulov, a kód sa premieta na plátno a sníma sa zo zadného radu, kde je
     * rozhodujúca veľkosť modulu, nie odolnosť proti poškodeniu.
     */
    public function imageForUrl(string $url, int $size = 620, int $margin = 0): GdImage
    {
        $result = (new Builder(
            writer: new PngWriter(),
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: $margin,
            foregroundColor: new Color(11, 18, 32),
            backgroundColor: new Color(255, 255, 255),
        ))->build();

        // POZOR na poradie: `getString()` prevedie obrázok na paletu priamo
        // v pamäti (PngWriter má predvolene 16 farieb), takže po ňom by
        // `getImage()` vrátil už znehodnotený obrázok.
        if ($result instanceof GdResult) {
            return $result->getImage();
        }

        $image = imagecreatefromstring($result->getString());

        if ($image === false) {
            abort(500, 'QR kód sa nepodarilo vykresliť.');
        }

        return $image;
    }
}
