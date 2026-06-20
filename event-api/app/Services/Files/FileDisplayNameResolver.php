<?php

namespace App\Services\Files;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FileDisplayNameResolver
{
    public function resolve(Model $model, string $originalName): string
    {
        $modelName = $this->resolveModelName($model);
        if ($modelName !== null) {
            return $modelName;
        }

        return $this->humanizeOriginalName($originalName);
    }

    private function resolveModelName(Model $model): ?string
    {
        $name = $model->getAttribute('name');
        if (! is_string($name)) {
            return null;
        }

        $name = trim($name);

        return $name !== '' ? $name : null;
    }

    private function humanizeOriginalName(string $originalName): string
    {
        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        $decoded = urldecode((string) $filename);

        $humanized = Str::of($decoded)
            ->replaceMatches('/[_-]+/', ' ')
            ->replaceMatches('/\s+/', ' ')
            ->trim(" .\t\n\r\0\x0B")
            ->value();

        return $humanized !== '' ? $humanized : 'Súbor';
    }
}
