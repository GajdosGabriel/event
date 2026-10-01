<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexFilterRequest;
use App\Http\Requests\OrganizationStoreRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\Traits\HasAllowedStatuses;
use App\Models\Canal;
use App\Models\Organization;
use App\Repositories\Contracts\OrganizationRepository;
use App\Services\Account\AccountClient;
use App\Services\Account\OrganizationSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DashboardOrganizationController extends Controller
{
    use HasAllowedStatuses;

    protected OrganizationRepository $organizationRepository;

    public function __construct(
        OrganizationRepository $organizationRepository,
        private readonly OrganizationSync $accountSync,
    ) {
        $this->organizationRepository = $organizationRepository;
    }

    public function index(IndexFilterRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Organization::class);

        $filters = $request->getFilters();
        $data = $this->organizationRepository->dashboardIndexWithFilters($filters['per_page'], $filters);

        return OrganizationResource::collection($data)
            ->additional([
                'meta' => [
                    'permissions' => [
                        'create' => request()->user()?->can('create', Organization::class) ?? false,
                    ],
                    'allowed_statuses' => $this->allowedStatuses($request),
                ],
            ]);
    }

    /**
     * Detail spolu s fakturačnými údajmi z Accountu.
     *
     * Account sa číta až tu, nie v zozname — inak by výpis organizácií
     * spravil toľko HTTP volaní, koľko je riadkov.
     */
    public function show(string $id): JsonResponse
    {
        $organization = $this->organizationRepository->dashboardShow($id);
        $this->authorize('view', $organization);

        // Fakturačné údaje (IČO, DIČ, IBAN) nie sú pre celý tím — dramaturg
        // vidí profil organizátora, ale nie firemné doklady. Account sa preto
        // ani nevolá, keď na ne používateľ právo nemá.
        $account = $this->authorizesBilling($organization)
            ? $this->accountSync->pull($organization)
            : null;

        return response()->json(
            (new OrganizationResource($organization))->withAccount($account)
        );
    }

    public function store(OrganizationStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Organization::class);

        $canal = $this->resolveCanal($request);

        // Zápis do Accountu je v tej istej transakcii: keď Account odmietne
        // IČO, nesmie v Evente zostať polovičná organizácia bez fakturačných
        // údajov, ktorú by používateľ musel dohľadávať a mazať.
        [$organization, $account] = DB::transaction(function () use ($request, $canal) {
            $organization = $this->organizationRepository->create($request->organizationData());

            // Väzba na kanál patrí do tej istej transakcie ako firma. Prístup
            // do dashboardu ide cez kanály (EloquentOrganizationRepository),
            // takže nenaviazaná organizácia by bola pre svojho autora
            // okamžite neviditeľná — ani zmazať by ju nevedel.
            $this->linkCanal($canal, $organization);

            $account = $this->accountSync->push($organization, $request->accountData());

            return [$organization, $account];
        });

        return response()->json(
            (new OrganizationResource($organization))->withAccount($account),
            201
        );
    }

    public function update(string $id, OrganizationStoreRequest $request): JsonResponse
    {
        $organization = $this->organizationRepository->dashboardShow($id);
        $this->authorize('update', $organization);

        // Naviazanie ďalšieho kanála je nepovinné — firma ich môže mať viac
        // (divízie, značky) a fakturuje sa za ne spolu.
        $canal = $request->canalId() !== null ? $this->resolveCanal($request) : null;

        [$organization, $account] = DB::transaction(function () use ($id, $request, $canal) {
            $organization = $this->organizationRepository->update($id, $request->organizationData());

            if ($canal !== null) {
                $this->linkCanal($canal, $organization);
            }

            $account = $this->accountSync->push($organization, $request->accountData());

            return [$organization, $account];
        });

        return response()->json(
            (new OrganizationResource($organization))->withAccount($account)
        );
    }

    /**
     * Vyhľadanie firmy v registri (RPO/ARES) podľa IČO — na predvyplnenie
     * formulára. Register volá Account, aby sa Event nemusel starať
     * o dve rôzne štátne API.
     */
    public function lookupIco(Request $request, AccountClient $account): JsonResponse
    {
        $this->authorize('create', Organization::class);

        $request->validate([
            'ico' => ['required', 'string', 'max:12'],
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        return response()->json($account->lookupIco(
            $request->string('ico')->toString(),
            $request->string('country', 'sk')->toString(),
        ));
    }

    public function destroy(string $id): JsonResponse
    {
        $organization = $this->organizationRepository->dashboardShow($id);
        $this->authorize('delete', $organization);

        $this->organizationRepository->delete($id);

        return response()->json(null, 204);
    }

    public function restore(string $id): JsonResponse
    {
        $this->organizationRepository->dashboardShowForRestore($id);

        $organization = $this->organizationRepository->restore($id);

        return response()->json(new OrganizationResource($organization));
    }

    /**
     * Odpojí kanál od firmy — prestane pod ňou fakturovať a spadne
     * na neplatený režim. Kanál ani jeho obsah sa nemaže.
     *
     * Poslednú väzbu odpojiť nemožno: prístup k firme v dashboarde vedie cez
     * kanály, takže by si používateľ zamkol vlastné dvere. Pre skutočné
     * zrušenie je tu mazanie organizácie.
     */
    public function detachCanal(string $id, string $canalId): JsonResponse
    {
        $organization = $this->organizationRepository->dashboardShow($id);
        $this->authorize('update', $organization);

        $canal = Canal::where('organization_id', $organization->getKey())->findOrFail($canalId);
        $this->authorize('update', $canal);

        if ($organization->canals()->count() <= 1) {
            throw ValidationException::withMessages([
                'canal_id' => __('organizations.errors.last_canal'),
            ]);
        }

        $canal->forceFill(['organization_id' => null])->save();
        request()->user()?->forgetCanalRoles();

        return response()->json(null, 204);
    }

    /**
     * Priradí ďalší kanál pod firmu. Jedna firma ich môže mať viac — divízie
     * či značky, ktoré sa fakturujú spolu.
     */
    public function attachCanal(string $id, Request $request): JsonResponse
    {
        $organization = $this->organizationRepository->dashboardShow($id);
        $this->authorize('update', $organization);

        $validated = $request->validate([
            'canal_id' => ['required', 'integer', 'exists:canals,id'],
        ]);

        $canal = Canal::findOrFail($validated['canal_id']);
        $this->authorize('update', $canal);

        $this->linkCanal($canal, $organization);

        return response()->json(null, 204);
    }

    /**
     * Kanál, pod ktorý firma patrí.
     *
     * Bez explicitného `canal_id` sa berie osobný kanál používateľa — ten má
     * po registrácii každý (PersonalCanalProvisioner), takže bežný organizátor
     * nemusí o väzbe vedieť. Právo sa overuje policy nad kanálom: firmu smie
     * pod kanál priradiť len ten, kto ten kanál aj spravuje.
     */
    private function resolveCanal(OrganizationStoreRequest $request): Canal
    {
        $canalId = $request->canalId() ?? $request->user()?->canal_id;

        if ($canalId === null) {
            throw ValidationException::withMessages([
                'canal_id' => __('organizations.errors.canal_required'),
            ]);
        }

        $canal = Canal::findOrFail($canalId);
        $this->authorize('update', $canal);

        return $canal;
    }

    /**
     * Priradí kanál pod organizáciu. `forceFill` preto, že `organization_id`
     * nechodí z formulára kanála a nemá čo byť v jeho fillable.
     */
    private function linkCanal(Canal $canal, Organization $organization): void
    {
        $canal->forceFill(['organization_id' => $organization->getKey()])->save();

        // Väzby sú memoizované na inštancii používateľa
        // (User::organizationCanalMap). Bez zhodenia cache by odpoveď niesla
        // práva spočítané ešte spred naviazania.
        request()->user()?->forgetCanalRoles();
    }

    /** Smie používateľ vidieť fakturačné údaje z Accountu? */
    private function authorizesBilling(Organization $organization): bool
    {
        return request()->user()?->can('viewBilling', $organization) ?? false;
    }
}
