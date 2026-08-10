<?php

namespace App\Models\Traits;

use App\Models\AttributeCheck;
use App\Models\User;
use App\Services\Attributes\AttributeCheckService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Model má hodnoty, ktorých funkčnosť sa dá strojovo overiť (dnes `website`).
 *
 * Evidencia beží z modelu, nie z controllera: zistiť, či odkaz z importu žije,
 * je slušnosť voči návštevníkovi a nikoho neobťažuje. Preto sa eviduje všetko,
 * nech to zapísal formulár, import alebo konzola.
 *
 * Obťažovať by mohlo až upozornenie — to preto odchádza len tam, kde je komu
 * (viď attributeIssueRecipient(); importované záznamy majiteľa nemajú).
 *
 * Model musí byť zapísaný v AttributeCheck::TARGETS.
 */
trait HasCheckedAttributes
{
    public static function bootHasCheckedAttributes(): void
    {
        static::saved(function (Model $model): void {
            app(AttributeCheckService::class)->sync($model);
        });

        static::deleted(function (Model $model): void {
            // Soft delete stav neruší — archivovaný záznam sa môže vrátiť
            // a overovať jeho odkaz nanovo je zbytočná práca. Ruší ho až
            // skutočné zmazanie, inak by riadky ostali visieť na neexistujúcom
            // modeli a príkaz by ich donekonečna skúšal overiť.
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $model->attributeChecks()->delete();
        });
    }

    /**
     * Hodnoty, ktoré sa na tomto modeli overujú. Model môže zoznam zúžiť
     * alebo rozšíriť; musí ísť o názvy atribútov so sondou v registri
     * (App\Services\Attributes\AttributeCheckService).
     *
     * @return array<int, string>
     */
    public function checkedAttributes(): array
    {
        return [AttributeCheck::WEBSITE];
    }

    /** @return MorphMany<AttributeCheck> */
    public function attributeChecks(): MorphMany
    {
        return $this->morphMany(AttributeCheck::class, 'checkable');
    }

    /** Stav overenia jednej hodnoty, alebo null ak sa zatiaľ neeviduje. */
    public function attributeCheck(string $attribute): ?AttributeCheck
    {
        return $this->attributeChecks->firstWhere('attribute', $attribute)
            ?? $this->attributeChecks()->where('attribute', $attribute)->first();
    }

    /**
     * Komu sa ozveme, keď hodnota prestane fungovať.
     *
     * Predvolene ten, kto dostáva správy cez „Poslať správu" — je to tá istá
     * otázka („kto sa o tento záznam stará?") a odpoveď na ňu už raz padla,
     * vrátane pravidiel, kedy nie je nikto (import, cudzí zdroj). Model bez
     * Messageable si metódu doplní sám.
     */
    public function attributeIssueRecipient(): ?User
    {
        return method_exists($this, 'messageRecipient') ? $this->messageRecipient() : null;
    }
}
