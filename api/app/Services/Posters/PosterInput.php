<?php

namespace App\Services\Posters;

use Illuminate\Http\UploadedFile;

/**
 * Vstup pre AI spojený zo sprievodného textu a súborov (PosterTextExtractor::combine).
 * Rovnaký pre nahratie na webe aj pre e-mail.
 */
class PosterInput
{
    /**
     * @param  PosterExtraction|null  $extraction  null = nie je z čoho čítať
     * @param  UploadedFile|null  $primaryFile  prvý prečítaný súbor — uloží sa ako plagát
     * @param  array<int, string>  $errors  prečo sa ktorý súbor nedal prečítať
     */
    public function __construct(
        public readonly ?PosterExtraction $extraction,
        public readonly ?UploadedFile $primaryFile = null,
        public readonly array $errors = [],
    ) {}
}
