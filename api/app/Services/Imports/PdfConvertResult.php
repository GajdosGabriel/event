<?php

namespace App\Services\Imports;

class PdfConvertResult
{
    public readonly string $fullText;

    /**
     * @param array<int, array{page: int, image: string, text: string}> $pages
     */
    public function __construct(
        public readonly int $pageCount,
        public readonly array $pages,
    ) {
        $this->fullText = implode("\n\n", array_values(array_filter(array_map(
            static fn (array $p) => trim((string) ($p['text'] ?? '')),
            $pages
        ))));
    }
}
