<?php

namespace App\Services\Posters;

use App\Services\Imports\PdfConverterService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Prečíta nahratý plagát a pripraví z neho vstup pre AI.
 *
 * Podporované formáty a spôsob čítania:
 *  - PDF  → externý konvertor (text + PNG každej strany); keď je textová vrstva
 *           prázdna (skenovaný plagát), rozhodne obrázok strany cez vision
 *  - DOCX → priamo zo ZIPu (`word/document.xml`); bez ďalšej závislosti,
 *           `phpoffice/phpword` by sme ťahali kvôli jednému súboru v archíve
 *  - TXT  → text tak, ako je
 *  - JPG/PNG/WEBP → rovno na vision, textová vrstva neexistuje
 *
 * `.doc` (starý binárny Word) podporovaný nie je — jeho parsovanie je
 * neúmerne drahé a export do PDF/DOCX vie každý Word.
 */
class PosterTextExtractor
{
    /** Toľko strán PDF ide na vision. Podstatné je takmer vždy na prvej. */
    private const MAX_VISION_PAGES = 3;

    /**
     * Nad túto veľkosť sa obrázok do promptu neposiela. Musí sedieť s limitom
     * v `PosterAnalyzeRequest` (max:12288) — nižšia hodnota by fotku z mobilu
     * odmietla až po dokončenom uploade, čo je pre človeka najhorší možný moment.
     */
    private const MAX_IMAGE_BYTES = 12 * 1024 * 1024;

    public function __construct(
        private readonly PdfConverterService $pdfConverter = new PdfConverterService(),
    ) {}

    public function fromText(string $text): PosterExtraction
    {
        $text = $this->normalizeText($text);

        if (trim($text) === '') {
            throw new PosterExtractionException(__('poster.extract.empty_text'));
        }

        return new PosterExtraction(text: $text, kind: 'text');
    }

    public function fromUploadedFile(UploadedFile $file): PosterExtraction
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $mime = strtolower((string) ($file->getMimeType() ?: $file->getClientMimeType()));

        if (str_starts_with($mime, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $this->fromImage($file, $mime);
        }

        if ($extension === 'pdf' || str_contains($mime, 'pdf')) {
            return $this->fromPdf($file);
        }

        if ($extension === 'docx' || str_contains($mime, 'wordprocessingml')) {
            return $this->fromDocx($file);
        }

        if (in_array($extension, ['txt', 'md'], true) || str_starts_with($mime, 'text/')) {
            return $this->fromText((string) file_get_contents($file->getRealPath()));
        }

        if ($extension === 'doc') {
            throw new PosterExtractionException(__('poster.extract.doc_legacy'));
        }

        throw new PosterExtractionException(__('poster.extract.unsupported'));
    }

    private function fromImage(UploadedFile $file, string $mime): PosterExtraction
    {
        $binary = (string) file_get_contents($file->getRealPath());

        if ($binary === '') {
            throw new PosterExtractionException(__('poster.extract.image_unreadable'));
        }

        if (strlen($binary) > self::MAX_IMAGE_BYTES) {
            throw new PosterExtractionException(__('poster.extract.image_too_large'));
        }

        $mime = str_starts_with($mime, 'image/') ? $mime : 'image/jpeg';

        return new PosterExtraction(
            text: '',
            imageDataUrls: ['data:' . $mime . ';base64,' . base64_encode($binary)],
            kind: 'image',
        );
    }

    private function fromPdf(UploadedFile $file): PosterExtraction
    {
        $binary = (string) file_get_contents($file->getRealPath());

        $converterLimit = (int) config('services.pdf_converter.max_upload_bytes', 0);
        if ($converterLimit > 0 && strlen($binary) > $converterLimit) {
            throw new PosterExtractionException(__('poster.extract.pdf_too_large_limit', [
                'limit' => (int) round($converterLimit / 1048576),
            ]));
        }

        $failureStatus = null;
        $result = $this->pdfConverter->convertFromBinary(
            $binary,
            $file->getClientOriginalName() ?: 'plagat.pdf',
            $failureStatus,
        );

        if ($result === null) {
            // Konvertor je externá služba (PDF_CONVERTER_URL). Keď nebeží, nemá
            // token alebo súbor odmietne, nemáme z PDF ani text, ani obrázky
            // strán — volať AI naprázdno nemá zmysel.
            Log::warning('PosterTextExtractor: PDF sa nepodarilo skonvertovať.', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'converter_status' => $failureStatus,
            ]);

            // 413 vracia nginx pred konvertorom, keď je upload nad jeho
            // `client_max_body_size`. Je to trvalý stav pre tento súbor —
            // radiť „skúste to znova" by človeka posielalo do kruhu.
            throw new PosterExtractionException(__($failureStatus === 413
                ? 'poster.extract.pdf_too_large'
                : 'poster.extract.pdf_failed'));
        }

        $text = $this->normalizeText($result->fullText);
        $extraction = new PosterExtraction(
            text: $text,
            imageDataUrls: [],
            kind: 'pdf',
            pageCount: $result->pageCount,
        );

        // Obrázky strán berieme len vtedy, keď textová vrstva nestačí. Keď má
        // PDF poriadny text, vision by pridal náklad bez pridanej informácie.
        if ($extraction->hasUsableText()) {
            return $extraction;
        }

        // Konvertor ťahá text cez `pdftotext` a keď poppler na jeho serveri
        // chýba, vráti obrázky strán a text `null` — teda presne to isté, ako
        // keby PDF textovú vrstvu nemalo. Rozdiel je zásadný: pri skutočnom
        // skene je vision jediná možnosť, tu ide o výpadok cudzej služby, ktorý
        // nás pripraví o presný text (a tým aj o popis podujatia).
        // Preto to skúsime ešte lokálne, kým siahneme po vision.
        $localText = $this->normalizeText($this->extractTextLocally($binary));

        if (mb_strlen($localText) > mb_strlen($text)) {
            Log::info('PosterTextExtractor: text z PDF dodal lokálny parser, nie konvertor.', [
                'filename' => $file->getClientOriginalName(),
                'converter_chars' => mb_strlen($text),
                'local_chars' => mb_strlen($localText),
            ]);

            $text = $localText;
            $extraction = new PosterExtraction(
                text: $text,
                imageDataUrls: [],
                kind: 'pdf',
                pageCount: $result->pageCount,
            );

            if ($extraction->hasUsableText()) {
                return $extraction;
            }
        }

        $imageDataUrls = [];

        foreach (array_slice($result->pages, 0, self::MAX_VISION_PAGES) as $page) {
            $pageBinary = $this->pdfConverter->decodePageImage((array) $page);
            if ($pageBinary === null || strlen($pageBinary) > self::MAX_IMAGE_BYTES) {
                continue;
            }
            $imageDataUrls[] = 'data:image/png;base64,' . base64_encode($pageBinary);
        }

        if ($imageDataUrls === [] && trim($text) === '') {
            throw new PosterExtractionException(__('poster.extract.pdf_empty'));
        }

        return new PosterExtraction(
            text: $text,
            imageDataUrls: $imageDataUrls,
            kind: 'pdf',
            pageCount: $result->pageCount,
        );
    }

    /**
     * Záloha za konvertor: text priamo z PDF, bez externej služby.
     *
     * Vyžaduje `smalot/pdfparser` (`composer require smalot/pdfparser`). Balík
     * je voliteľný — keď nie je nainštalovaný, funkcia vráti prázdno a pipeline
     * pokračuje na vision presne ako doteraz. Preto `class_exists()`, nie
     * tvrdá závislosť: bez neho appka beží ďalej, len horšie.
     */
    private function extractTextLocally(string $binary): string
    {
        if (! class_exists(\Smalot\PdfParser\Parser::class)) {
            return '';
        }

        try {
            return (string) (new \Smalot\PdfParser\Parser())->parseContent($binary)->getText();
        } catch (\Throwable $e) {
            Log::debug('PosterTextExtractor: lokálna extrakcia textu z PDF zlyhala.', [
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * DOCX je ZIP s XML. Čítame len `word/document.xml` — hlavičky a pätičky
     * obsahujú logo a číslovanie strán, nie údaje o podujatí.
     */
    private function fromDocx(UploadedFile $file): PosterExtraction
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new PosterExtractionException(__('poster.extract.zip_missing'));
        }

        $zip = new \ZipArchive();

        if ($zip->open($file->getRealPath()) !== true) {
            throw new PosterExtractionException(__('poster.extract.docx_unreadable'));
        }

        try {
            $xml = $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }

        if (! is_string($xml) || $xml === '') {
            throw new PosterExtractionException(__('poster.extract.docx_no_text'));
        }

        $text = $this->normalizeText($this->docxXmlToText($xml));

        if (trim($text) === '') {
            throw new PosterExtractionException(__('poster.extract.no_text'));
        }

        return new PosterExtraction(text: $text, kind: 'docx');
    }

    private function docxXmlToText(string $xml): string
    {
        // Odstavce a zalomenia musia prežiť ako nové riadky: dátum a čas sú
        // v plagáte často na samostatných riadkoch a bez nich by sa zliali
        // do jedného slova ("18:0021. augusta").
        $xml = preg_replace('~<w:(?:br|cr)\s*/?>~i', "\n", $xml) ?? $xml;
        $xml = preg_replace('~<w:tab\s*/?>~i', ' ', $xml) ?? $xml;
        $xml = preg_replace('~</w:p>~i', "\n", $xml) ?? $xml;
        $xml = preg_replace('~</w:tc>~i', "\t", $xml) ?? $xml;
        $xml = preg_replace('~</w:tr>~i', "\n", $xml) ?? $xml;

        $text = strip_tags($xml);

        return html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        $text = (string) preg_replace('/[^\P{C}\n\t]+/u', '', $text);

        return trim($text);
    }
}
