<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexFilterRequest;
use App\Http\Requests\OrganizationStoreRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\Traits\HasAllowedStatuses;
use App\Models\Organization;
use App\Repositories\Contracts\OrganizationRepository;
use App\Services\Account\AccountClient;
use App\Services\Account\OrganizationSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

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

        return response()->json(
            (new OrganizationResource($organization))->withAccount($this->accountSync->pull($organization))
        );
    }

    public function store(OrganizationStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Organization::class);

        // Zápis do Accountu je v tej istej transakcii: keď Account odmietne
        // IČO, nesmie v Evente zostať polovičná organizácia bez fakturačných
        // údajov, ktorú by používateľ musel dohľadávať a mazať.
        [$organization, $account] = DB::transaction(function () use ($request) {
            $organization = $this->organizationRepository->create($request->organizationData());
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

        [$organization, $account] = DB::transaction(function () use ($id, $request) {
            $organization = $this->organizationRepository->update($id, $request->organizationData());
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
}
