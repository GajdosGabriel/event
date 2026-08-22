<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\FileType;
use App\Enums\ModelStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FileStoreRequest;
use App\Http\Requests\FileUpdateRequest;
use App\Http\Resources\FileResource;
use App\Models\Canal;
use App\Models\Event;
use App\Models\File;
use App\Models\Venue;
use App\Services\Files\FileManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;

class DashboardFileController extends Controller
{
    private const FILEABLE_MAP = [
        'canal' => Canal::class,
        'event' => Event::class,
        'venue' => Venue::class,
    ];

    public function __construct(private readonly FileManager $fileManager) {}

    /**
     * List files.
     *
     * S `fileable_id` ide o prílohy jedného záznamu, ako ich ťahá galéria na
     * detaile. Bez neho o všetky súbory používateľa — tie, čo visia na jeho
     * kanáloch, ich podujatiach a miestach.
     *
     * Query params: fileable_type (canal|event|venue), fileable_id, search, with_trashed
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', File::class);

        $request->validate([
            // Typ je povinný len pri dopyte na konkrétny záznam; vo výpise
            // celého dashboardu je to nepovinný filter.
            'fileable_type' => [$request->filled('fileable_id') ? 'required' : 'sometimes', 'string', 'in:canal,event,venue'],
            'fileable_id'   => ['sometimes', 'integer', 'min:1'],
            'search'        => ['sometimes', 'string', 'max:100'],
            'with_trashed'  => ['sometimes', 'boolean'],
        ]);

        if (! $request->filled('fileable_id')) {
            return $this->indexForUser($request);
        }

        $modelClass = self::FILEABLE_MAP[$request->fileable_type];
        $model = $modelClass::findOrFail($request->fileable_id);

        $this->authorize('view', $model);

        $files = $model->files()->paginate(20);

        return FileResource::collection($files)
            ->additional([
                'meta' => [
                    'permissions' => [
                        'create' => $request->user()?->can('update', $model) ?? false,
                    ],
                ],
            ]);
    }

    /**
     * Všetky súbory v dosahu používateľa. Rozsah sa odvodzuje od jeho kanálov,
     * nie z policy per riadok — tá by musela bežať nad celou tabuľkou. Práva na
     * jednotlivé akcie nesie každý riadok vo `permissions` z FileResource.
     */
    private function indexForUser(Request $request): AnonymousResourceCollection
    {
        $canalIds = $request->user()->dashboardCanalIds();

        $types = $request->filled('fileable_type')
            ? [self::FILEABLE_MAP[$request->fileable_type]]
            : array_values(self::FILEABLE_MAP);

        $query = File::query();

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        $query->where(function ($outer) use ($types, $canalIds) {
            foreach ($types as $type) {
                $outer->orWhere(fn ($sub) => $sub
                    ->where('fileable_type', $type)
                    ->whereIn('fileable_id', $this->ownedIdsQuery($type, $canalIds)));
            }
        });

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->string('search') . '%');
        }

        $files = $query->latest()->paginate(30);

        $this->attachFileableNames($files->getCollection());

        return FileResource::collection($files);
    }

    /**
     * Kľúče záznamov daného typu, ktoré patria do kanálov používateľa. Rozsahy
     * kopírujú dashboardové zoznamy: podujatie visí na kanáli priamo, miesto cez
     * publikovanú väzbu `canal_venue` (viď EloquentVenueRepository).
     */
    private function ownedIdsQuery(string $type, Collection $canalIds)
    {
        return match ($type) {
            Canal::class => Canal::withTrashed()->whereIn('id', $canalIds)->select('id'),
            Event::class => Event::withTrashed()->whereIn('canal_id', $canalIds)->select('id'),
            Venue::class => Venue::withTrashed()
                ->whereHas('canals', fn ($q) => $q
                    ->whereIn('canals.id', $canalIds)
                    ->where('canal_venue.status', ModelStatus::Published->value))
                ->select('venues.id'),
        };
    }

    /**
     * Doplní k súborom názov záznamu, na ktorom visia. Zámerne nie cez
     * `with('fileable')` — Event si v `$appends` ťahá vlastné súbory, obrázky
     * aj kanál, takže eager load morphTo by z výpisu urobil desiatky dotazov.
     *
     * @param  Collection<int, File>  $files
     */
    private function attachFileableNames(Collection $files): void
    {
        $names = [];

        foreach (array_values(self::FILEABLE_MAP) as $type) {
            $ids = $files->where('fileable_type', $type)->pluck('fileable_id')->unique()->filter();

            if ($ids->isEmpty()) {
                continue;
            }

            $names[$type] = $type::withTrashed()->whereIn('id', $ids)->pluck('name', 'id');
        }

        foreach ($files as $file) {
            $file->setAttribute(
                'fileable_name',
                $names[$file->fileable_type][$file->fileable_id] ?? null,
            );
        }
    }

    public function show(string $id): JsonResponse
    {
        $file = File::findOrFail($id);
        $this->authorize('view', $file);

        return response()->json(new FileResource($file));
    }

    public function store(FileStoreRequest $request): JsonResponse
    {
        $modelClass = self::FILEABLE_MAP[$request->fileable_type];
        $model = $modelClass::findOrFail($request->fileable_id);

        $this->authorize('update', $model);

        $type = FileType::tryFrom($request->input('type', FileType::IMAGE->value)) ?? FileType::IMAGE;

        $files = $model->addFiles(
            files: $request->file('files', []),
            type: $type,
            disk: $request->input('disk', config('filesystems.default', 'public')),
            makePrimary: $request->boolean('make_primary', false),
        );

        return response()->json(FileResource::collection($files), 201);
    }

    public function update(string $id, FileUpdateRequest $request): JsonResponse
    {
        $file = File::findOrFail($id);
        $this->authorize('update', $file);

        if ($request->has('is_primary') && $request->boolean('is_primary')) {
            $file = $this->fileManager->setPrimary($file);
        } else {
            $file->update($request->only(['is_primary', 'sort_order', 'meta']));
            $file->refresh();
        }

        return response()->json(new FileResource($file));
    }

    public function destroy(string $id): JsonResponse
    {
        $file = File::findOrFail($id);
        $this->authorize('delete', $file);

        $this->fileManager->delete($file, false);

        return response()->json(null, 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items'            => ['required', 'array'],
            'items.*.id'       => ['required', 'integer'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($request->input('items') as $item) {
            $file = File::find($item['id']);
            if ($file) {
                $this->authorize('update', $file);
                $file->update(['sort_order' => $item['sort_order']]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function restore(string $id): JsonResponse
    {
        $file = File::withTrashed()->findOrFail($id);
        $this->authorize('restore', $file);

        $file->restore();
        $file->refresh();

        return response()->json(new FileResource($file));
    }
}
