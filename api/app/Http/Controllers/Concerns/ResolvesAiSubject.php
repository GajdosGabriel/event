<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Canal;
use App\Models\Event;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;

/**
 * Preklad `kind` z požiadavky na triedu modelu.
 *
 * Zoznam je whitelist, nie mapovanie: `kind` chodí z prehliadača a bez neho by
 * sa dal jedným parametrom vypýtať posudok ľubovoľnej triedy v aplikácii.
 */
trait ResolvesAiSubject
{
    /** @var array<string, class-string<Model>> */
    private const AI_SUBJECTS = [
        'event' => Event::class,
        'venue' => Venue::class,
        'canal' => Canal::class,
    ];

    /** @return class-string<Model> */
    protected function aiSubjectClass(string $kind): string
    {
        return self::AI_SUBJECTS[$kind] ?? abort(422, 'Neznámy typ záznamu.');
    }
}
