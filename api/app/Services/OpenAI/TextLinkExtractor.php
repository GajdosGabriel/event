<?php

namespace App\Services\OpenAI;

class TextLinkExtractor
{
    public function extract(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $pattern = '/\b((?:https?:\/\/|www\.)[^\s<>"\'\)\]]+)/i';
        preg_match_all($pattern, $text, $matches);

        if (empty($matches[1])) {
            return [];
        }

        $links = [];
        foreach ($matches[1] as $rawUrl) {
            $cleanUrl = $this->normalizeUrl($rawUrl);
            if (!$cleanUrl) {
                continue;
            }

            $links[$cleanUrl] = true;
        }

        return array_keys($links);
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        $url = rtrim($url, ".,;:!?)\"]");

        if (str_starts_with($url, 'www.')) {
            $url = 'https://' . $url;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $url;
    }
}
