<?php

namespace App\Jobs;

use App\Enums\FileType;
use App\Models\Canal;
use App\Services\Files\FileManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stiahne profilovku z Google/Facebooku a nastaví ju ako hlavný obrázok
 * osobného kanála.
 *
 * Beží vo fronte zámerne — sťahovanie je HTTP volanie na cudzí server a
 * prihlásenie cez sociálnu sieť naň nesmie čakať. Zlyhanie sa iba zaloguje:
 * účet aj kanál už existujú a chýbajúci avatar nie je dôvod na chybu.
 */
class ImportSocialAvatarJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $canalId,
        public readonly string $avatarUrl,
    ) {}

    public function handle(FileManager $files): void
    {
        $canal = Canal::find($this->canalId);

        if (! $canal || $this->avatarUrl === '') {
            return;
        }

        // Druhé prihlásenie ani opakovanie jobu nesmie kanálu pridať avatar,
        // ktorý si používateľ medzičasom zmazal alebo vymenil za vlastný.
        if ($canal->files()->where('type', FileType::IMAGE->value)->exists()) {
            return;
        }

        try {
            $files->storeRemoteForModel(
                model: $canal,
                attachments: [[
                    'url' => $this->avatarUrl,
                    'name' => 'avatar.jpg',
                ]],
                type: FileType::IMAGE,
                makePrimary: true,
                meta: ['social_avatar' => true],
            );
        } catch (Throwable $e) {
            Log::warning('Nepodarilo sa stiahnuť avatar zo sociálnej siete.', [
                'canal_id' => $this->canalId,
                'url' => $this->avatarUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
