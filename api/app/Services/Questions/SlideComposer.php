<?php

namespace App\Services\Questions;

use App\Enums\FileType;
use App\Models\File;
use App\Models\QuestionBoard;
use App\Support\BoardToken;
use App\Support\PublicUrl;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Poskladá obsah snímky z nástenky — preloží a naformátuje všetko, čo
 * SlideRenderer už len kreslí.
 */
class SlideComposer
{
    public function compose(QuestionBoard $board): SlideContent
    {
        $event = $board->event();
        $isWorkshop = $board->targetType() === 'workshop';
        $canal = $event?->canal;

        $url = PublicUrl::questionBoard($board->token);

        return new SlideContent(
            eyebrow: __('questions.slide.eyebrow'),
            title: (string) $board->title(),
            // Pri workshope patrí názov podujatia pod nadpis; pri nástenke
            // celého podujatia by sa tam meno zopakovalo, tak tam ide organizátor.
            subtitle: $isWorkshop ? $event?->name : $canal?->name,
            when: $this->whenLabel($event?->start_at, $event?->end_at),
            where: $this->whereLabel($event),
            organizer: $canal?->name,
            // Na plátne stojí adresa bez schémy a s pomlčkou v kóde —
            // `A7K2M-9QXBF` sa prepisuje o poznanie spoľahlivejšie než
            // jedenásť znakov v jednom kuse. Pomlčku BoardToken::normalize()
            // pri príchode zahodí, takže adresa naozaj funguje tak, ako je
            // napísaná, a nemusí sa vedľa nej tlačiť ešte samostatný kód.
            url: preg_replace('#^https?://#', '', PublicUrl::questionBoard(BoardToken::forDisplay($board->token))) ?? $url,
            qrUrl: $url,
            cta: __('questions.slide.cta'),
            photo: $this->photoBytes($event, $canal),
        );
    }

    /**
     * „Streda 17. 8. 2026, 18:00 – 21:00". Názov dňa aj mesiaca berie Carbon
     * z aktuálneho jazyka — ten nastavuje SetLocale z hlavičky, prípadne
     * parameter `?lang=` na sťahovacej adrese.
     */
    private function whenLabel(?Carbon $start, ?Carbon $end): ?string
    {
        if ($start === null) {
            return null;
        }

        Carbon::setLocale(app()->getLocale());

        $label = ucfirst($start->translatedFormat('l j. n. Y, H:i'));

        if ($end === null) {
            return $label;
        }

        return $start->isSameDay($end)
            ? $label . ' – ' . $end->format('H:i')
            : $label . ' – ' . $end->translatedFormat('j. n. Y, H:i');
    }

    private function whereLabel(?Model $event): ?string
    {
        if ($event === null) {
            return null;
        }

        $parts = array_filter([
            $event->venue?->name,
            $event->municipality?->name,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Bajty fotky na pozadie a do odznaku. Prednosť má vlastný obrázok
     * podujatia, potom obrázok kanála.
     *
     * Číta sa varianta `large` (dlhšia strana 1280 px), nikdy originál: ten
     * môže mať 4000×3000 a v pamäti by to bolo takmer 50 MB. Predvolené
     * obrázky v `public/images/*.svg` sa sem nedostanú vôbec — GD SVG nečíta
     * a snímka na ich mieste kreslí monogram.
     */
    private function photoBytes(?Model $event, ?Model $canal): ?string
    {
        foreach ([$event, $canal] as $owner) {
            if ($owner === null) {
                continue;
            }

            $bytes = $this->primaryImageBytes($owner);

            if ($bytes !== null) {
                return $bytes;
            }
        }

        return null;
    }

    private function primaryImageBytes(Model $owner): ?string
    {
        /** @var File|null $file */
        $file = $owner->files()
            ->where('type', FileType::IMAGE->value)
            ->where('is_primary', true)
            ->first();

        if ($file === null) {
            return null;
        }

        $path = $file->large ?: $file->path;

        if (! is_string($path) || $path === '') {
            return null;
        }

        try {
            $disk = Storage::disk((string) ($file->disk ?? config('filesystems.default', 'public')));

            return $disk->exists($path) ? $disk->get($path) : null;
        } catch (\Throwable) {
            // Nedostupné úložisko nesmie zhodiť snímku — bez fotky sa vykreslí
            // monogram a organizátor si toho ani nemusí všimnúť.
            return null;
        }
    }
}
