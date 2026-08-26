<?php

namespace App\Services\Publishing;

use App\Enums\ModelStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Zámok odpublikovania kanála a miesta.
 *
 * Kanál ani miesto, na ktoré sa už odvoláva podujatie, sa nesmú stiahnuť
 * z verejného výpisu — odkaz z podujatia by viedol do prázdna. Prekážku počíta
 * model (App\Models\Traits\ProtectsReferencedRecords), tento strážca ju len
 * postaví do cesty všetkým spôsobom, ako sa stav dá zmeniť:
 *
 *  - tlačidlom (publish endpoint -> RecordPublisher),
 *  - <select>-om v editácii (repozitáre kanála a miesta).
 *
 * Bez druhého miesta by formulár prvé jednoducho obišiel.
 *
 * Archivácia zámku nepodlieha: archív znamená „mimo prevádzky", nie zmazané —
 * záznam ostáva dohľadateľný a odkaz z podujatia nikam nespadne. Viď
 * VenuePolicy::update().
 *
 * Nie je to otázka práva, ale stavu dát, preto odchádza ako 422 s vysvetlením,
 * nie ako holé 403. V dashboarde to zachytí už policy; v /admin nie, tam má
 * super-admin bypass (AuthServiceProvider) — a práve preto zámok musí byť aj tu.
 */
class UnpublishGuard
{
    /**
     * @param  string|null  $newStatus  stav, na ktorý sa záznam prepisuje;
     *                                  null = formulár status vôbec neposlal
     */
    public function assert(Model $model, ?string $newStatus): void
    {
        if ($newStatus !== ModelStatus::Draft->value) {
            return;
        }

        $this->assertUnpublishable($model);
    }

    public function assertUnpublishable(Model $model): void
    {
        $current = $model->status instanceof ModelStatus
            ? $model->status
            : ModelStatus::tryFrom((string) $model->status);

        if ($current !== ModelStatus::Published) {
            return;
        }

        if (! method_exists($model, 'unpublishBlocker')) {
            return;
        }

        if ($blocker = $model->unpublishBlocker()) {
            abort(422, $blocker);
        }
    }
}
