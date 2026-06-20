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
}
