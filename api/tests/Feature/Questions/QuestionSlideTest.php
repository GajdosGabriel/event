<?php

namespace Tests\Feature\Questions;

use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Enums\SlideTheme;
use App\Enums\SlideVariant;
use App\Models\QuestionBoard;
use App\Services\Questions\SlideSpec;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestSupport\EventSetupTest;
use ZipArchive;

class QuestionSlideTest extends EventSetupTest
{
    private function publishedBoard(): QuestionBoard
    {
        $this->app['auth']->forgetGuards();

        $this->futureEvent->update([
            'status' => ModelStatus::Published,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(3),
        ]);

        return $this->futureEvent->ensureQuestionBoard();
    }

    /** Malý obrázok v pamäti — cena vykreslenia závisí od cieľa, nie od zdroja. */
    private function jpegBinary(int $width = 400, int $height = 300): string
    {
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            imagefilledrectangle($image, 0, $y, $width - 1, $y, imagecolorallocate($image, 30 + $y % 200, 90, 160));
        }

        ob_start();
        imagejpeg($image, null, 85);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private function attachPrimaryImage(QuestionBoard $board): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('canal/1/image/photo.jpg', $this->jpegBinary());

        $this->futureEvent->files()->create([
            'name' => 'photo.jpg',
            'original_name' => 'photo.jpg',
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1000,
            'disk' => 'public',
            'path' => 'canal/1/image/photo.jpg',
            'large' => 'canal/1/image/photo.jpg',
            'type' => FileType::IMAGE->value,
            'is_primary' => true,
        ]);
    }

    #[Test]
    public function slide_is_returned_as_a_full_hd_png(): void
    {
        $board = $this->publishedBoard();

        $response = $this->get("/api/q/{$board->token}/slide.png");
        $response->assertOk()->assertHeader('Content-Type', 'image/png');

        [$width, $height, $type] = getimagesizefromstring($response->getContent());

        $this->assertSame(1920, $width);
        $this->assertSame(1080, $height);
        $this->assertSame(IMAGETYPE_PNG, $type);
        $this->assertStringContainsString('attachment; filename="otazky-', $response->headers->get('Content-Disposition'));
    }

    #[Test]
    public function square_variant_has_square_dimensions(): void
    {
        $board = $this->publishedBoard();

        $response = $this->get("/api/q/{$board->token}/slide.png?variant=square");
        [$width, $height] = getimagesizefromstring($response->getContent());

        $this->assertSame(1080, $width);
        $this->assertSame(1080, $height);
    }

    /**
     * Jednofarebná plocha v mieste QR kódu znamená, že sa nevykreslil — a to je
     * chyba, ktorú by rozmerový test nezachytil.
     */
    #[Test]
    public function the_qr_card_really_contains_a_qr_code(): void
    {
        $board = $this->publishedBoard();

        $png = $this->get("/api/q/{$board->token}/slide.png")->getContent();
        $image = imagecreatefromstring($png);
        $spec = SlideSpec::for(SlideVariant::Slide, SlideTheme::Dark);

        $darkest = 255;
        $lightest = 0;

        for ($y = $spec->qrY(); $y < $spec->qrY() + $spec->qrSize; $y += 7) {
            for ($x = $spec->qrX(); $x < $spec->qrX() + $spec->qrSize; $x += 7) {
                $luminance = $this->luminance($image, $x, $y);
                $darkest = min($darkest, $luminance);
                $lightest = max($lightest, $luminance);
            }
        }

        $this->assertLessThan(60, $darkest, 'V mieste QR kódu chýbajú tmavé moduly.');
        $this->assertGreaterThan(200, $lightest, 'V mieste QR kódu chýba biele pozadie.');
    }

    /**
     * Tichá zóna okolo QR kódu je vnútorné odsadenie karty. Štandard žiada
     * štyri moduly, čo je pri bežnom kóde okolo 14 % jeho veľkosti — keby to
     * neskoršie „utiahnutie layoutu" zmenšilo, snímka by vyzerala rovnako
     * a prestala by sa skenovať.
     */
    #[Test]
    public function every_variant_keeps_the_quiet_zone_around_the_qr(): void
    {
        foreach (SlideVariant::cases() as $variant) {
            $spec = SlideSpec::for($variant, SlideTheme::Dark);

            $this->assertGreaterThanOrEqual(
                $spec->qrSize * 0.14,
                $spec->cardInnerPad,
                "Tichá zóna je pri variante {$variant->value} príliš úzka.",
            );
        }
    }

    /**
     * Premietacia stena potrebuje samotný QR — zmenšená snímka by tam bola
     * nenaskenovateľná miniatúra.
     */
    #[Test]
    public function standalone_qr_is_square_and_carries_its_own_quiet_zone(): void
    {
        $board = $this->publishedBoard();

        $response = $this->get("/api/q/{$board->token}/qr.png?size=480");
        $response->assertOk()->assertHeader('Content-Type', 'image/png');

        $png = $response->getContent();
        [$width, $height] = getimagesizefromstring($png);

        $this->assertSame($width, $height);
        // Tichá zóna je 6 % veľkosti na každej strane, takže výstup je väčší
        // než požadovaných 480 px a rohový pixel musí byť biely.
        $this->assertGreaterThan(480, $width);

        $image = imagecreatefromstring($png);
        $this->assertGreaterThan(240, $this->luminance($image, 2, 2));

        // Nezmyselná veľkosť sa oreže do rozumného rozsahu, nie 500-kuje.
        $this->get("/api/q/{$board->token}/qr.png?size=99999")->assertOk();
        $this->get("/api/q/{$board->token}/qr.png?size=1")->assertOk();
    }

    #[Test]
    public function themes_differ_from_each_other(): void
    {
        $board = $this->publishedBoard();

        $dark = imagecreatefromstring($this->get("/api/q/{$board->token}/slide.png?theme=dark")->getContent());
        $light = imagecreatefromstring($this->get("/api/q/{$board->token}/slide.png?theme=light")->getContent());

        $this->assertLessThan(90, $this->luminance($dark, 40, 40));
        $this->assertGreaterThan(200, $this->luminance($light, 40, 40));
    }

    #[Test]
    public function unknown_variant_or_theme_is_rejected_instead_of_silently_falling_back(): void
    {
        $board = $this->publishedBoard();

        $this->get("/api/q/{$board->token}/slide.png?variant=nezmysel")->assertStatus(422);
        $this->get("/api/q/{$board->token}/slide.png?theme=nezmysel")->assertStatus(422);
    }

    #[Test]
    public function slide_survives_a_missing_broken_or_hostile_input(): void
    {
        $board = $this->publishedBoard();

        // Kanál bez fotky — musí sa vykresliť monogram, nie prázdne miesto.
        $this->get("/api/q/{$board->token}/slide.png")->assertOk();

        // Poškodený obrázok.
        Storage::fake('public');
        Storage::disk('public')->put('canal/1/image/broken.jpg', 'toto nie je obrázok');
        $this->futureEvent->files()->create([
            'name' => 'broken.jpg', 'original_name' => 'broken.jpg', 'extension' => 'jpg',
            'mime_type' => 'image/jpeg', 'size' => 20, 'disk' => 'public',
            'path' => 'canal/1/image/broken.jpg', 'large' => 'canal/1/image/broken.jpg',
            'type' => FileType::IMAGE->value, 'is_primary' => true,
        ]);
        $this->get("/api/q/{$board->token}/slide.png")->assertOk();

        // Veľmi dlhý názov s diakritikou a emoji.
        $this->futureEvent->update([
            'name' => '🎉 Medzinárodná konferencia o umelej inteligencii a jej dopade na malé a stredné podniky v regióne 🎊',
        ]);
        $this->get("/api/q/{$board->token}/slide.png")->assertOk();
        $this->get("/api/q/{$board->token}/slide.png?variant=square")->assertOk();
    }

    #[Test]
    public function slide_renders_with_a_real_photo(): void
    {
        $board = $this->publishedBoard();
        $this->attachPrimaryImage($board);

        $response = $this->get("/api/q/{$board->token}/slide.png");
        $response->assertOk();

        [$width, $height] = getimagesizefromstring($response->getContent());
        $this->assertSame(1920, $width);
        $this->assertSame(1080, $height);

        // Poistka proti „a čo keby sme čítali originál namiesto varianty large" —
        // fotka 4000×3000 by v pamäti zabrala takmer 50 MB.
        $this->assertLessThan(160 * 1024 * 1024, memory_get_peak_usage(true));
    }

    #[Test]
    public function draft_event_has_no_downloadable_slide(): void
    {
        $board = $this->publishedBoard();
        $this->futureEvent->update(['status' => ModelStatus::Draft]);

        $this->get("/api/q/{$board->token}/slide.png")->assertNotFound();
        $this->get("/api/q/{$board->token}/slide.pptx")->assertNotFound();
    }

    #[Test]
    public function pptx_is_a_single_slide_deck_powerpoint_can_open(): void
    {
        $board = $this->publishedBoard();

        $response = $this->get("/api/q/{$board->token}/slide.pptx");
        $response->assertOk()->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        );

        $tmp = tempnam(sys_get_temp_dir(), 'pptxtest');
        file_put_contents($tmp, $response->getContent());

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);

        $required = [
            '[Content_Types].xml', '_rels/.rels',
            'ppt/presentation.xml', 'ppt/_rels/presentation.xml.rels',
            'ppt/slideMasters/slideMaster1.xml', 'ppt/slideMasters/_rels/slideMaster1.xml.rels',
            'ppt/slideLayouts/slideLayout1.xml', 'ppt/slideLayouts/_rels/slideLayout1.xml.rels',
            'ppt/slides/slide1.xml', 'ppt/slides/_rels/slide1.xml.rels',
            'ppt/theme/theme1.xml', 'ppt/media/image1.png',
        ];

        foreach ($required as $part) {
            $this->assertNotFalse($zip->locateName($part), "V balíku chýba časť {$part}.");

            if (str_ends_with($part, '.xml')) {
                $this->assertNotFalse(
                    simplexml_load_string((string) $zip->getFromName($part)),
                    "Časť {$part} nie je platné XML — pravdepodobne neescapovaný znak v názve podujatia.",
                );
            }
        }

        $slide = (string) $zip->getFromName('ppt/slides/slide1.xml');
        $this->assertStringContainsString('cx="12192000"', $slide);
        $this->assertStringContainsString('cy="6858000"', $slide);

        // `r:embed` musí ukazovať na obrázok, nie na layout. Zámena je najčastejšia
        // chyba a prejaví sa prázdnou snímkou, nie chybou.
        preg_match('/r:embed="(rId\d+)"/', $slide, $matches);
        $rels = (string) $zip->getFromName('ppt/slides/_rels/slide1.xml.rels');
        $this->assertMatchesRegularExpression(
            '/Id="' . preg_quote($matches[1], '/') . '"[^>]*Target="\.\.\/media\/image1\.png"/',
            $rels,
        );

        $this->assertStringStartsWith("\x89PNG", (string) $zip->getFromName('ppt/media/image1.png'));

        $zip->close();
        @unlink($tmp);
    }

    #[Test]
    public function pptx_survives_an_event_name_with_xml_characters(): void
    {
        $board = $this->publishedBoard();
        $this->futureEvent->update(['name' => 'Rock & Roll <noc> "naživo"']);

        $response = $this->get("/api/q/{$board->token}/slide.pptx");
        $response->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'pptxtest');
        file_put_contents($tmp, $response->getContent());

        $zip = new ZipArchive();
        $zip->open($tmp);
        $this->assertNotFalse(simplexml_load_string((string) $zip->getFromName('ppt/slides/slide1.xml')));
        $this->assertNotFalse(simplexml_load_string((string) $zip->getFromName('docProps/core.xml')));
        $zip->close();
        @unlink($tmp);
    }

    private function luminance(\GdImage $image, int $x, int $y): int
    {
        $rgb = imagecolorat($image, $x, $y);

        return (int) round(
            0.2126 * (($rgb >> 16) & 0xFF)
            + 0.7152 * (($rgb >> 8) & 0xFF)
            + 0.0722 * ($rgb & 0xFF)
        );
    }
}
