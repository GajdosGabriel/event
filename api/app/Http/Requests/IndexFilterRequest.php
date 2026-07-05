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
            'sort' => ['nullable', 'in:newest,oldest,name,upcoming'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
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
            'sort' => $this->input('sort'),
            'date_from' => $this->input('date_from'),
            'date_to' => $this->input('date_to'),
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

    private function getSearchFilter(): ?string
    {
        $search = trim((string) $this->input('search', ''));

        return $search !== '' ? $search : null;
    }
}
