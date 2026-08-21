<?php

namespace App\Services\Publishing;

use App\Enums\ModelStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Preklopenie statusu miesta alebo kanála.
 *
 * Podujatie má na to vlastnú cestu (EloquentEventRepository::publish) — rieši
 * navyše `publish_at` naplánovaného publikovania. Miesto a kanál sú jednoduché:
 * status a, ak ho tabuľka má, čas prvého zverejnenia.
 */
class RecordPublisher
{
    public function apply(Model $model, bool $publish): Model
    {
        $attributes = [
            'status' => $publish ? ModelStatus::Published->value : ModelStatus::Draft->value,
        ];

        // `venues` stĺpec published_at nemá, `canals` áno. Raz zapísaný čas
        // ostáva — je to história prvého zverejnenia, nie príznak stavu.
        if (array_key_exists('published_at', $model->getAttributes())) {
            $attributes['published_at'] = $publish
                ? ($model->published_at ?? now())
                : null;
        }

        $model->forceFill($attributes)->save();

        return $model;
    }
}
