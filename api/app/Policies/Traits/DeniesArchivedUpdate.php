<?php

namespace App\Policies\Traits;

use App\Enums\ModelStatus;

trait DeniesArchivedUpdate
{
    protected function isNotArchived(object $model): bool
    {
        return ! (
            isset($model->status) &&
            $model->status instanceof ModelStatus &&
            $model->status->isArchived()
        );
    }
}
