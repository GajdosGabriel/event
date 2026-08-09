<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AttributeCheck;
use App\Services\Attributes\AttributeCheckService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * „Niekto práve klikol na tento odkaz" z verejnej stránky.
 *
 * Nie je to hlásenie chyby, hoci to tak z názvu znie — prehliadač po kliknutí
 * odíde na cudziu doménu a či sa tam niečo načítalo, sa z našej stránky zistiť
 * nedá (cross-origin). Je to podnet: o tento odkaz niekto stojí, over ho hneď.
 * Rozhodne až sonda na serveri (AttributeCheckService).
 *
 * Bezpečnostné jadro celej veci: **od klienta sa neberie žiadna adresa.**
 * Príde len typ a id záznamu, hodnotu si server nájde sám v databáze. Inak by
 * z toho bola brána, cez ktorú si ktokoľvek nechá náš server zaklopať na
 * ľubovoľnú adresu (SSRF) — a k tomu ešte adresu do e-mailu majiteľovi.
 */
class BrokenLinkReportController extends Controller
{
    public function __construct(
        private readonly AttributeCheckService $service,
    ) {
    }

    /**
     * Odpoveď je vždy rovnaká a bez obsahu — volá sa cez `sendBeacon()` pri
     * odchode zo stránky, takže ju nikto nečíta, a rozlišovať „našlo/nenašlo"
     * by len ponúklo spôsob, ako si otestovať existenciu id.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(AttributeCheck::TARGETS))],
            'id' => ['required', 'integer', 'min:1'],
            'attribute' => ['nullable', 'string', Rule::in([AttributeCheck::WEBSITE])],
            'from' => ['nullable', 'string', 'max:255'],
        ]);

        $model = $this->resolve($validated['type'], (int) $validated['id']);

        if ($model !== null) {
            $this->service->report(
                $model,
                $validated['attribute'] ?? AttributeCheck::WEBSITE,
                $this->safePath($validated['from'] ?? null),
            );
        }

        return response()->json(status: 202);
    }

    private function resolve(string $type, int $id): ?Model
    {
        /** @var class-string<Model> $class */
        $class = AttributeCheck::TARGETS[$type];

        return $class::query()->find($id);
    }

    /**
     * Z hodnoty `from` prepustí len cestu na našej stránke.
     *
     * Ide do e-mailu majiteľovi, takže cudzia adresa by z upozornenia urobila
     * doručovací mechanizmus pre odkazy kohokoľvek. Query string sa zahadzuje
     * — do adresy stránky nepatria osobné údaje a v e-maile ich netreba.
     */
    private function safePath(?string $from): ?string
    {
        $from = trim((string) $from);

        if ($from === '') {
            return null;
        }

        // `//evil.sk` prehliadač chápe ako cudziu doménu, nie ako cestu.
        if (! str_starts_with($from, '/') || str_starts_with($from, '//')) {
            return null;
        }

        $path = strtok($from, '?#');

        if (! is_string($path) || ! preg_match('#^/[\p{L}\p{N}\-._~/]*$#u', $path)) {
            return null;
        }

        return mb_substr($path, 0, 191);
    }
}
