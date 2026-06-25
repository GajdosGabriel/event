<?php

namespace App\Http\Resources\Traits;

use App\Enums\ModelStatus;
use Illuminate\Http\Request;

trait HasAllowedStatuses
{
    protected function allowedStatuses(Request $request): array
    {
        return ModelStatus::allowedForUser($request->user());
    }

    protected function statusLabel(): string
    {
        $status = $this->status instanceof ModelStatus
            ? $this->status
            : ModelStatus::tryFrom((string) $this->status);

        return $status?->label() ?? (string) ($this->status?->value ?? $this->status);
    }
}
