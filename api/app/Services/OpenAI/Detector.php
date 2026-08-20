<?php

namespace App\Services\OpenAI;

use App\Models\Canal;
use App\Models\Venue;
use App\Services\Geocoding\NominatimGeocoder;
use App\Services\Imports\ImportedNameMatcher;
use App\Services\Geocoding\MunicipalityNameFinder;
use App\Services\Geocoding\MunicipalityResolver;
use App\Services\Places\WikipediaPlaceEnricher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Detector
{
    public function __construct(
        private readonly WebPageFetcher $fetcher = new WebPageFetcher(),
        private readonly ContentExtractor $contentExtractor = new ContentExtractor(),
        private readonly ChatGPT $chatGPT = new ChatGPT(),
        private readonly NominatimGeocoder $nominatimGeocoder = new NominatimGeocoder(),
        private readonly MunicipalityResolver $municipalityResolver = new MunicipalityResolver(),
        private readonly WikipediaPlaceEnricher $wikipediaPlaceEnricher = new WikipediaPlaceEnricher(),
        private readonly AttachmentDownloader $attachmentDownloader = new AttachmentDownloader(),
        private readonly TextLinkExtractor $textLinkExtractor = new TextLinkExtractor(),
        private readonly MunicipalityNameFinder $municipalityNameFinder = new MunicipalityNameFinder(),
    ) {}


    public function stiahniTextCurl(string $url): string
    {
        return $this->fetcher->fetch($url);
    }

    public function extrahujContentBody(string $html, string $baseUrl): array
    {
        return $this->contentExtractor->extract($html, $baseUrl);
    }

    public function stiahniPrilohy(array $attachments, int $eventId): array
    {
        return $this->attachmentDownloader->download($attachments, $eventId);
    }

    public function extrahujLinkyZTextu(string $text): array
    {
        return $this->textLinkExtractor->extract($text);
    }

    /**
     * Spracuje surový text eventu:
     * - opraví a rozšíri ho cez copywritera
     * - extrahuje štruktúrované dáta (title, dátumy, organizer, venue, persons)
     * - vyhľadá alebo navrhne canal pre organizátora
     * - detekuje detaily venue cez AI + Nominatim pipeline
     * - vráti navrhnuté canaly pre každú osobu z textu
     */
    public function detectFromText(string $text): array
    {
        return $this->detectFromPoster($text);
    }

    /**
     * To isté ako detectFromText(), ale s obrázkami plagátu navyše.
     *
     * Nahratý plagát je typicky grafika: text z neho buď nevytiahneme vôbec
     * (JPG, sken), alebo len útržky. Obrázky preto idú priamo do modelu spolu
     * s tým, čo sa z textovej vrstvy podarilo prečítať.
     *
     * @param  array<int, string>  $imageDataUrls  `data:image/...;base64,…` URL
     */
    public function detectFromPoster(string $text, array $imageDataUrls = []): array
    {
        try {
            $eventPayload = $this->chatGPT->extractDataFromPoster($text, $imageDataUrls);

            // Prepis plagátu nepatrí medzi polia formulára — `event_payload` sa
            // v UI predvypĺňa kľúč po kľúči a text plagátu by tam bol pole
            // navyše. Ide vlastnou cestou ako náhrada chýbajúcej textovej vrstvy.
            $posterText = $this->firstString($eventPayload['poster_text'] ?? null);
            unset($eventPayload['poster_text']);

            $eventPayload = $this->fillMissingVenueCity($eventPayload);

            // Copywriter je čisto textový. Pri obrázkovom plagáte nemá z čoho
            // vychádzať — jediný text, ktorý o ňom máme, je prepis z vision.
            // Bez neho ostal popis podujatia prázdny aj pri plagáte, ktorý mal
            // celý program vysádzaný v grafike.
            $sourceText = mb_strlen(trim($text)) >= 50 ? $text : ($posterText ?? $text);

            $correctedText = null;
            try {
                if (mb_strlen(trim($sourceText)) >= 50) {
                    $copywriter = $this->chatGPT->extractCopywriter($sourceText);
                    $correctedText = $copywriter['event_body'] ?? null;
                }
            } catch (\Throwable $e) {
                // Zlyhanie copywritera nie je fatálne — volajúci má fallback na
                // surový text. Bez logu sa to ale nedalo vôbec zistiť: navonok
                // to vyzeralo, akoby dokument popis neobsahoval.
                Log::warning('Copywriter neprepísal text podujatia.', [
                    'text_length' => mb_strlen(trim($sourceText)),
                    'from_poster_text' => $sourceText !== $text,
                    'error' => $e->getMessage(),
                ]);
            }

            $organizerName = $this->resolveOrganizerCanalName($eventPayload);
            $organizerCanal = $organizerName ? $this->lookupCanalByName($organizerName) : null;

            $venueDetect = null;
            $venueName = $eventPayload['venue']['name'] ?? null;
            $venueCity = $eventPayload['venue']['city'] ?? null;
            if (is_string($venueName) && $venueName !== '' && is_string($venueCity) && $venueCity !== '') {
                $existingVenue = $this->lookupVenueByName($venueName);
                $venueDetect = $this->detectVenueDetails($venueName, $venueCity);
                $venueDetect['existing_venue'] = $existingVenue;
            }

            $persons = [];
            foreach ($eventPayload['persons'] ?? [] as $person) {
                $personName = $person['meno'] ?? null;
                $persons[] = [
                    'name' => $personName,
                    'phone' => $person['telefon'] ?? null,
                    'email' => $person['email'] ?? null,
                    'description' => $person['description'] ?? null,
                    'existing_canal' => is_string($personName) && $personName !== ''
                        ? $this->lookupCanalByName($personName)
                        : null,
                ];
            }

            return [
                'success' => true,
                'corrected_text' => $correctedText,
                // Záloha za `corrected_text`: keď copywriter zlyhá, je prepis
                // plagátu jediný text, ktorý o obrázkovom plagáte máme.
                'poster_text' => $posterText,
                'event_payload' => $eventPayload,
                'organizer_canal' => [
                    'name' => $organizerName,
                    'existing' => $organizerCanal,
                ],
                'venue_detect' => $venueDetect,
                'persons' => $persons,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Doplní obec, keď ju model nevrátil ako samostatný údaj.
     *
     * Plagát mesto zvyčajne nepíše do vlastného riadku — býva v adrese
     * („Chrám Zosnutia presvätej Bohorodičky, Klokočov - Zemplínska Šírava")
     * alebo priamo v názve podujatia. Model ho potom vráti ako súčasť ulice
     * a `venue.city` ostane null. Dôsledky sú dva a oba nepríjemné: v
     * sprievodcovi ostane prázdne povinné pole „Mesto / obec" a `detectVenueDetails()`
     * sa vôbec nespustí (chce názov aj mesto), takže miesto príde bez adresy,
     * súradníc aj popisu.
     *
     * Preto sa obec hľadá v číselníku — nie v celom texte plagátu, ale len
     * v poliach, ktoré sa miesta naozaj týkajú. Hádať obec z náhodnej vety by
     * podujatie odsunulo do iného okresu, a to je horšie než prázdne pole.
     *
     * @param  array<string, mixed>  $eventPayload
     * @return array<string, mixed>
     */
    private function fillMissingVenueCity(array $eventPayload): array
    {
        $venue = $eventPayload['venue'] ?? null;

        if (! is_array($venue) || $this->firstString($venue['city'] ?? null) !== null) {
            return $eventPayload;
        }

        $street = $this->firstString($venue['street_and_number'] ?? null);

        $sources = [
            'street_and_number' => $street,
            'venue_name' => $this->firstString($venue['name'] ?? null),
            'title' => $this->firstString($eventPayload['title'] ?? null),
        ];

        foreach ($sources as $source => $value) {
            $city = $this->municipalityNameFinder->find($value);

            if ($city === null) {
                continue;
            }

            $venue['city'] = $city;

            // Adresný riadok bez čísla domu nie je ulica — „Klokočov - Zemplínska
            // Šírava" je poloha, ktorú sme práve preložili na obec. Nechať ho
            // v `street_and_number` by na detaile miesta vyrobilo adresu
            // „Klokočov - Zemplínska Šírava, Klokočov".
            if ($source === 'street_and_number' && preg_match('/\d/', (string) $value) !== 1) {
                $venue['street_and_number'] = null;
            }

            Log::info('Detector: obec doplnená z číselníka.', [
                'source' => $source,
                'city' => $city,
            ]);

            break;
        }

        $eventPayload['venue'] = $venue;

        return $eventPayload;
    }

    private function lookupCanalByName(string $name): ?array
    {
        $normalizedName = $this->normalizeLookupName($name);
        $slug = Str::slug($normalizedName);

        $canal = Canal::query()
            ->where(function ($query) use ($normalizedName, $slug) {
                $query->where('slug', $slug)
                    ->orWhere('name', $normalizedName)
                    ->orWhere('name', 'like', '%' . $normalizedName . '%');
            })
            ->orderByDesc('created_at')
            ->first(['id', 'name', 'slug']);

        // Rovnaká zhoda na holom slugu ako v ImportedCanalManager, aby nápoveda
        // v admine ukázala ten istý kanál, na ktorý napojí import.
        $canal ??= ImportedNameMatcher::firstByBaseName(Canal::query(), $normalizedName);

        if (! $canal instanceof Canal) {
            return null;
        }

        return ['id' => $canal->id, 'name' => $canal->name, 'slug' => $canal->slug];
    }

    private function lookupVenueByName(string $name): ?array
    {
        $normalizedName = $this->normalizeLookupName($name);
        $slug = Str::slug($normalizedName);

        $venue = Venue::query()
            ->where(function ($query) use ($normalizedName, $slug) {
                $query->where('slug', $slug)
                    ->orWhere('name', $normalizedName)
                    ->orWhere('name', 'like', '%' . $normalizedName . '%');
            })
            ->orderByDesc('created_at')
            ->first(['id', 'name', 'slug']);

        if (! $venue instanceof Venue) {
            return null;
        }

        return ['id' => $venue->id, 'name' => $venue->name, 'slug' => $venue->slug];
    }

    private function normalizeLookupName(string $value): string
    {
        $normalized = str_replace(["\xE2\x80\x93", "\xE2\x80\x94"], '-', $value);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function resolveOrganizerCanalName(array $eventPayload): ?string
    {
        $organizerCandidate = $this->firstString(
            $eventPayload['organizer']['name'] ?? null,
            $eventPayload['organization'] ?? null,
        );

        $venueCandidate = $this->firstString(
            $eventPayload['venue']['name'] ?? null,
            $eventPayload['building'] ?? null,
        );

        if ($organizerCandidate === null) {
            return $venueCandidate;
        }

        if ($venueCandidate !== null && $this->looksLikePersonalName($organizerCandidate)) {
            return $venueCandidate;
        }

        foreach ($eventPayload['persons'] ?? [] as $person) {
            $personName = isset($person['meno']) && is_string($person['meno']) ? trim($person['meno']) : null;
            $description = isset($person['description']) && is_string($person['description'])
                ? mb_strtolower(trim($person['description']))
                : null;

            if ($personName === null || $personName === '' || $description === null || $description === '') {
                continue;
            }

            if ($personName !== $organizerCandidate) {
                continue;
            }

            if (str_contains($description, 'pridal') || str_contains($description, 'pridala') || str_contains($description, 'pridal/a')) {
                return $venueCandidate ?? $organizerCandidate;
            }
        }

        return $organizerCandidate;
    }

    private function looksLikePersonalName(string $value): bool
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        if ($normalized === '') {
            return false;
        }

        $organizationKeywords = [
            'centrum',
            'zbor',
            'farnos',
            'obec',
            'mesto',
            'spolok',
            'zdruzen',
            'o.z.',
            's.r.o',
            'a.s.',
            'nadacia',
            'cirkev',
        ];

        $lower = mb_strtolower($normalized);
        foreach ($organizationKeywords as $keyword) {
            if (str_contains($lower, $keyword)) {
                return false;
            }
        }

        $parts = preg_split('/\s+/u', $normalized) ?: [];
        if (count($parts) < 2 || count($parts) > 4) {
            return false;
        }

        foreach ($parts as $part) {
            if (! preg_match('/^\p{Lu}[\p{L}\-\']+$/u', $part)) {
                return false;
            }
        }

        return true;
    }

    public function detectFromUrl(string $url): array
    {
        try {
            $html = $this->stiahniTextCurl($url);
            $extracted = $this->extrahujContentBody($html, $url);
            $extractedText = $extracted['text'] ?? '';
            $eventPayload = $this->chatGPT->extractData($extractedText);
            // $eventCopywriterPayload = $this->chatGPT->extractCopywriter($extractedText);
            if (empty($eventPayload)) {
                throw new \RuntimeException('AI vratilo prazdny payload');
            }

            return [
                'success' => true,
                'message' => 'Udalost analyzovana',
                'event_payload' => $eventPayload,
                // 'event_copywriter_payload' => $eventCopywriterPayload,
                'extracted_text' => $extractedText,
                'attachments' => $extracted['attachments'] ?? [],
                'links' => $this->extrahujLinkyZTextu($extractedText),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function detectVenueDetails(string $name, string $city, ?string $country = null): array
    {
        try {
            $input = [
                'name' => $name,
                'city' => $city,
                'country' => $country,
            ];

            $aiVenuePayload = $this->chatGPT->extractVenueDetails($input);
            $geocodedPayload = $this->nominatimGeocoder->lookup($name, $city, $country);
            $geocodedLookup = get_class($this->nominatimGeocoder) === NominatimGeocoder::class
                ? $this->nominatimGeocoder->lookupDetailed($name, $city, $country)
                : ['result' => $geocodedPayload, 'debug' => null];
            $venuePayload = $this->mergeVenuePayload($input, $aiVenuePayload, $geocodedPayload);

            $placeEnrichment = $this->wikipediaPlaceEnricher->enrich(
                $venuePayload['name'] ?? $name,
                $venuePayload['city'] ?? $city,
                $venuePayload['country'] ?? $country,
            );

            $enrichmentMatchesVenue = $this->areVenueNamesCompatible(
                $placeEnrichment['official_name'] ?? null,
                $venuePayload['name'] ?? $name,
                $venuePayload['city'] ?? $city,
                $venuePayload['country'] ?? $country,
            );

            if (is_string($placeEnrichment['official_name'] ?? null) && trim((string) $placeEnrichment['official_name']) !== '') {
                $officialName = trim((string) $placeEnrichment['official_name']);
                $cityName = $this->firstString(
                    $venuePayload['city'] ?? null,
                    $city,
                );

                // Keep the detected venue stable; enrichment may refine the name,
                // but it must not replace it with a different landmark from the same municipality.
                if (
                    ! $this->isSameText($officialName, $cityName)
                    && $enrichmentMatchesVenue
                ) {
                    $venuePayload['name'] = $officialName;
                }
            }

            $venuePayload['object_description'] = $this->firstString(
                $enrichmentMatchesVenue ? ($placeEnrichment['long_description'] ?? null) : null,
                $enrichmentMatchesVenue ? ($placeEnrichment['object_description'] ?? null) : null,
                $venuePayload['object_description'] ?? null,
            );

            $venuePayload['image_url'] = $this->firstString(
                $enrichmentMatchesVenue ? ($placeEnrichment['image_url'] ?? null) : null,
                $venuePayload['image_url'] ?? null,
            );

            // New fields: multiple images, logo, and contact info
            $venuePayload['image_urls'] = $enrichmentMatchesVenue
                ? $this->deduplicateImageUrls(
                    $placeEnrichment['image_urls'] ?? [],
                    $venuePayload['image_url'] ?? null,
                )
                : [];
            $venuePayload['logo_url'] = $this->firstString(
                $enrichmentMatchesVenue ? ($placeEnrichment['logo_url'] ?? null) : null,
            );
            $venuePayload['email'] = $this->firstString(
                $enrichmentMatchesVenue ? ($placeEnrichment['email'] ?? null) : null,
            );
            $venuePayload['phone'] = $this->firstString(
                $enrichmentMatchesVenue ? ($placeEnrichment['phone'] ?? null) : null,
            );

            $venuePayload['website'] = $this->firstString(
                $enrichmentMatchesVenue ? ($placeEnrichment['website'] ?? null) : null,
                $venuePayload['website'] ?? null,
            );

            $venuePayload['reference_url'] = $this->firstString(
                $enrichmentMatchesVenue ? ($placeEnrichment['reference_url'] ?? null) : null,
                $venuePayload['reference_url'] ?? null,
            );

            $venuePayload['enrichment_source'] = $this->firstString(
                $enrichmentMatchesVenue ? ($placeEnrichment['enrichment_source'] ?? null) : null,
                $venuePayload['enrichment_source'] ?? null,
            );

            // Obec z článku je autorita nad tým, KDE sa podujatie koná.
            // Geokóder je autorita nad ulicou a súradnicami budovy — nad
            // obcou nie. Kým sa `village_id` počítalo z $venuePayload['city'],
            // teda z firstString(geokóder, AI, článok), mesto z článku vždy
            // prehralo: na „Amfiteáter Košice" vrátil OSM amfiteáter
            // v Námestove a podujatie skončilo na Orave.
            $venuePayload = array_merge(
                $venuePayload,
                $this->resolveMunicipalityPreferringArticleCity(
                    $city,
                    $venuePayload['city'] ?? null,
                    $venuePayload['postcode'] ?? null,
                )
            );

            $venueStorePayload = $this->buildVenueStorePayload($venuePayload);

            return [
                'success' => true,
                'message' => 'Miesto analyzovane',
                'venue_payload' => $venuePayload,
                'venue_store_payload' => $venueStorePayload,
                'missing_required_fields' => $this->missingRequiredVenueStoreFields($venueStorePayload),
                'can_store_immediately' => $this->missingRequiredVenueStoreFields($venueStorePayload) === [],
                'debug' => [
                    'input' => $input,
                    'ai_payload' => $aiVenuePayload,
                    'final_venue_payload' => $venuePayload,
                    'geocoder' => $geocodedLookup['debug'] ?? null,
                    'geocoder_result' => $geocodedPayload,
                    'geocoder_name_compatible' => $this->areVenueNamesCompatible(
                        $geocodedPayload['name'] ?? null,
                        $name,
                        $city,
                        $country,
                    ),
                    'wikipedia' => [
                        'official_name' => $placeEnrichment['official_name'] ?? null,
                        'reference_url' => $placeEnrichment['reference_url'] ?? null,
                        'matched' => $enrichmentMatchesVenue,
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function mergeVenuePayload(array $input, array $aiPayload, array $geocodedPayload): array
    {
        $geocodedNameMatchesInput = $this->areVenueNamesCompatible(
            $geocodedPayload['name'] ?? null,
            $input['name'] ?? null,
            $input['city'] ?? null,
            $input['country'] ?? null,
        );

        return [
            'name' => $this->pickBestVenueName(
                $input['name'] ?? null,
                $aiPayload['name'] ?? null,
                $geocodedPayload['name'] ?? null,
                $input['city'] ?? null,
                $input['country'] ?? null,
            ),
            'street' => $this->firstString(
                $geocodedNameMatchesInput ? ($geocodedPayload['street'] ?? null) : null,
                $aiPayload['street'] ?? null,
            ),
            'postcode' => $this->firstString(
                $geocodedNameMatchesInput ? ($geocodedPayload['postcode'] ?? null) : null,
                $aiPayload['postcode'] ?? null,
            ),
            'city' => $this->firstString(
                $geocodedPayload['city'] ?? null,
                $aiPayload['city'] ?? null,
                $input['city'] ?? null,
            ),
            'country' => $this->firstString(
                $geocodedPayload['country'] ?? null,
                $aiPayload['country'] ?? null,
                $input['country'] ?? null,
            ),
            'latitude' => $this->firstFloat(
                $geocodedNameMatchesInput ? ($geocodedPayload['latitude'] ?? null) : null,
                $aiPayload['latitude'] ?? null,
            ),
            'longitude' => $this->firstFloat(
                $geocodedNameMatchesInput ? ($geocodedPayload['longitude'] ?? null) : null,
                $aiPayload['longitude'] ?? null,
            ),
        ];
    }

    private function pickBestVenueName(
        ?string $inputName,
        ?string $aiName,
        ?string $geocodedName,
        ?string $city = null,
        ?string $country = null,
    ): ?string {
        if ($this->areVenueNamesCompatible($aiName, $inputName, $city, $country)) {
            return $this->firstString($aiName, $inputName);
        }

        if ($this->areVenueNamesCompatible($geocodedName, $inputName, $city, $country)) {
            return $this->firstString($geocodedName, $inputName);
        }

        return $this->firstString($inputName, $aiName, $geocodedName);
    }

    private function firstString(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    private function firstFloat(mixed ...$values): ?float
    {
        foreach ($values as $value) {
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    private function isSameText(?string $left, ?string $right): bool
    {
        if (! is_string($left) || ! is_string($right)) {
            return false;
        }

        return mb_strtolower(trim($left)) === mb_strtolower(trim($right));
    }

    private function areVenueNamesCompatible(
        ?string $candidate,
        ?string $reference,
        ?string $city = null,
        ?string $country = null,
    ): bool {
        $candidateNormalized = $this->normalizeComparableText($candidate);
        $referenceNormalized = $this->normalizeComparableText($reference);

        if ($candidateNormalized === null || $referenceNormalized === null) {
            return false;
        }

        if (
            $candidateNormalized === $referenceNormalized
            || str_contains($candidateNormalized, $referenceNormalized)
            || str_contains($referenceNormalized, $candidateNormalized)
        ) {
            return true;
        }

        $ignoredTokens = array_unique(array_filter([
            ...$this->tokenizeComparableText($city),
            ...$this->tokenizeComparableText($country),
            'mesto',
            'obec',
            'v',
        ]));

        $candidateTokens = array_values(array_diff(
            $this->tokenizeComparableText($candidate),
            $ignoredTokens,
        ));
        $referenceTokens = array_values(array_diff(
            $this->tokenizeComparableText($reference),
            $ignoredTokens,
        ));

        if ($candidateTokens === [] || $referenceTokens === []) {
            return false;
        }

        return array_intersect($candidateTokens, $referenceTokens) !== [];
    }

    private function normalizeComparableText(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Str::ascii, nie iconv //TRANSLIT: ten na tomto builde prepisuje
        // dĺžne na apostrof s písmenom („Trenčín“ → „Trenc'in“), čo by tu
        // rozbilo porovnávanie názvov miest na spoločné slová.
        $ascii = Str::ascii($value);
        if ($ascii !== '') {
            $value = $ascii;
        }

        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return $value !== '' ? $value : null;
    }

    private function tokenizeComparableText(?string $value): array
    {
        $normalized = $this->normalizeComparableText($value);
        if ($normalized === null) {
            return [];
        }

        return array_values(array_filter(
            explode(' ', $normalized),
            static fn (string $token): bool => $token !== '' && strlen($token) >= 3
        ));
    }

    /**
     * Obec pre `village_id`: najprv mesto z článku, až potom to z geokódera.
     *
     * Číselník je presný a bez siete — keď naň mesto z článku sadne, je to
     * najlepší údaj, aký máme, a geokóder ho nemá čím prebiť. Až keď naň
     * nesadne (mestská časť, osada, pútnické miesto ako „Skalka pri
     * Trenčíne"), má zmysel siahnuť po obci, ktorú našiel geokóder.
     *
     * Nesúhlasné PSČ nevadí: MunicipalityResolver po neúspešnej dvojici
     * mesto+PSČ skúša ešte samotné mesto.
     *
     * @return array<string, mixed>
     */
    private function resolveMunicipalityPreferringArticleCity(
        ?string $articleCity,
        ?string $geocodedCity,
        ?string $postcode,
    ): array {
        if ($this->firstString($articleCity) !== null) {
            $fromArticle = $this->municipalityResolver->resolve($articleCity, $postcode);

            if (($fromArticle['village_id'] ?? null) !== null) {
                return $fromArticle;
            }
        }

        return $this->municipalityResolver->resolve($geocodedCity ?? $articleCity, $postcode);
    }

    private function buildVenueStorePayload(array $venuePayload): array
    {
        return [
            'village_id' => $venuePayload['village_id'] ?? null,
            'name' => $venuePayload['name'] ?? null,
            'street' => $venuePayload['street'] ?? null,
            'postcode' => $venuePayload['postcode'] ?? null,
            'body' => $venuePayload['object_description'] ?? null,
            'website' => $this->normalizeWebsiteForStore($venuePayload['website'] ?? null),
            'email' => $this->normalizeEmailForStore($venuePayload['email'] ?? null),
            'phone' => $this->normalizePhoneForStore($venuePayload['phone'] ?? null),
            'country' => $venuePayload['country'] ?? 'Slovakia',
            'latitude' => $venuePayload['latitude'] ?? null,
            'longitude' => $venuePayload['longitude'] ?? null,
            'capacity' => null,
            'opening_hours' => null,
            'category' => null,
            'status' => 'draft',
        ];
    }

    private function normalizeWebsiteForStore(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return strlen($trimmed) <= 100 ? $trimmed : null;
    }

    private function normalizeEmailForStore(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || strlen($trimmed) > 100) {
            return null;
        }

        return filter_var($trimmed, FILTER_VALIDATE_EMAIL) ? $trimmed : null;
    }

    private function normalizePhoneForStore(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || strlen($trimmed) > 20) {
            return null;
        }

        return $trimmed;
    }

    private function missingRequiredVenueStoreFields(array $venueStorePayload): array
    {
        $requiredFields = ['village_id', 'name'];

        return array_values(array_filter($requiredFields, function (string $field) use ($venueStorePayload) {
            $value = $venueStorePayload[$field] ?? null;

            if (is_string($value)) {
                return trim($value) === '';
            }

            return $value === null;
        }));
    }

    private function filterEmptyArray(array $array): array
    {
        return array_values(array_filter(
            $array,
            fn ($item) => is_string($item) && trim($item) !== ''
        ));
    }

    private function deduplicateImageUrls(array $imageUrls, ?string $primaryImageUrl = null): array
    {
        $normalizedPrimary = $this->normalizeImageUrl($primaryImageUrl);
        $seen = [];
        $result = [];

        foreach ($this->filterEmptyArray($imageUrls) as $imageUrl) {
            $normalized = $this->normalizeImageUrl($imageUrl);
            if ($normalized === null) {
                continue;
            }

            if ($normalizedPrimary !== null && $normalized === $normalizedPrimary) {
                continue;
            }

            if (isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $result[] = trim($imageUrl);
        }

        return $result;
    }

    private function normalizeImageUrl(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return mb_strtolower($trimmed);
    }
}
