<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexFilterRequest extends FormRequest
{
    private const BOOLEAN_FILTER_KEYS = [
        'published',
        'unpublished',
        'blocked',
        'deleted',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (self::BOOLEAN_FILTER_KEYS as $key) {
            if (! $this->has($key)) {
                continue;
            }

            $normalized[$key] = $this->normalizeBooleanFilter($this->input($key));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }

        // Štítky chodia z URL ako ?tags=koncert,folklor — do validácie ich
        // treba dostať ako pole.
        if ($this->has('tags') && ! is_array($this->input('tags'))) {
            $this->merge([
                'tags' => array_values(array_filter(
                    array_map('trim', explode(',', (string) $this->input('tags'))),
                    static fn (string $slug) => $slug !== '',
                )),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:draft,pending_review,rejected,scheduled,published,archived,blocked'],
            'search' => ['nullable', 'string', 'max:250'],
            'published' => ['nullable', 'boolean'],
            'unpublished' => ['nullable', 'boolean'],
            'blocked' => ['nullable', 'boolean'],
            'deleted' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'municipality' => ['nullable', 'integer', 'min:1'],
            'canal_id' => ['nullable', 'integer', 'min:1'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['string', 'max:60'],
            'sort' => ['nullable', 'in:newest,oldest,name,upcoming'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'phase' => ['nullable', 'in:active,running,today,next7d,past'],
        ];
    }

    public function getFilters(): array
    {
        return [
            'status' => $this->input('status'),
            'search' => $this->getSearchFilter(),
            'published' => $this->getPublishedFilter(),
            'blocked' => $this->getBooleanFilter('blocked'),
            'deleted' => $this->getBooleanFilter('deleted'),
            'per_page' => $this->input('per_page', 15),
            'municipality' => $this->input('municipality') ? (int) $this->input('municipality') : null,
            'canal_id' => $this->input('canal_id') ? (int) $this->input('canal_id') : null,
            'tags' => $this->getTagsFilter(),
            'sort' => $this->input('sort'),
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
            'phase' => $this->input('phase'),
        ];
    }

    private function getBooleanFilter(string $key): ?bool
    {
        if (! $this->has($key)) {
            return null;
        }

        return $this->boolean($key);
    }

    private function normalizeBooleanFilter(mixed $value): mixed
    {
        if (is_bool($value) || $value === null) {
            return $value;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $normalized ?? $value;
    }

    private function getPublishedFilter(): ?bool
    {
        if ($this->has('published')) {
            return $this->boolean('published');
        }
        if ($this->has('unpublished')) {
            return !$this->boolean('unpublished');
        }
        return null;
    }

    /**
     * @return array<int, string>|null
     */
    private function getTagsFilter(): ?array
    {
        $tags = $this->input('tags');

        if (! is_array($tags)) {
            return null;
        }

        $tags = array_values(array_filter(array_map(
            static fn ($slug) => trim((string) $slug),
            $tags,
        ), static fn (string $slug) => $slug !== ''));

        return $tags !== [] ? $tags : null;
    }

    private function getSearchFilter(): ?string
    {
        $search = trim((string) $this->input('search', ''));

        return $search !== '' ? $search : null;
    }
}
