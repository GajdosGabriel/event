<?php

namespace App\Exceptions;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

/**
 * Podujatie sa nedá publikovať, kým nie je publikované jeho miesto a kanál.
 *
 * Nie je to obyčajné 422: front z neho stavia ponuku „publikovať aj ich",
 * takže okrem vety potrebuje aj strojovo čitateľný zoznam. Chytá sa na `code`.
 */
class DependenciesNotPublishedException extends \RuntimeException implements Responsable
{
    public const CODE = 'dependencies_not_published';

    /**
     * @param array<int, array{type: string, id: int, name: string, status: string, label: string}> $dependencies
     */
    public function __construct(private readonly array $dependencies)
    {
        parent::__construct(__('events.errors.dependencies_not_published', [
            'names' => implode(' + ', array_column($dependencies, 'label')),
        ]));
    }

    public function toResponse($request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => self::CODE,
            'dependencies' => $this->dependencies,
        ], 422);
    }
}
