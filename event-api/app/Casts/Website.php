<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class Website implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set($model, string $key, $value, array $attributes): ?string
    {
        return $this->normalizeUrl($value);
    }

    protected function normalizeUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        // Doplní https:// ak chýba
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $parsed = parse_url($url);
        if (empty($parsed['host'])) {
            return null;
        }

        $scheme = $parsed['scheme'] ?? 'https';
        $host = $parsed['host'];

        return $scheme . '://' . $host;
    }
}
