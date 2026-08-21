<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Telo publish endpointu miesta a kanála. Podujatie má vlastný
 * EventPublishRequest — tam sa pri publikovaní ešte dovaliduje obsah.
 */
class PublishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'published' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * `published: false` znamená zrušenie publikovania; bez príznaku sa
     * publikuje — rovnaká konvencia ako pri podujatí.
     */
    public function shouldPublish(): bool
    {
        return $this->boolean('published', true);
    }
}
