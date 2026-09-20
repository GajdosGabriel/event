<?php

namespace App\Http\Requests;

use App\Enums\TicketTypeKind;
use App\Enums\TicketTypeKindOption;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketTypeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorizácia proti podujatiu prebieha v controlleri (event.update).
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:190'],
            'kind' => ['sometimes', Rule::enum(TicketTypeKind::class)],
            'description' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'price_amount' => ['nullable', 'integer', 'min:0'],
            'price_currency' => ['sometimes', 'string', 'size:3'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'max_per_order' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'min_per_order' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'requires_attendee_name' => ['sometimes', 'boolean'],
            'open_to_public' => ['sometimes', 'boolean'],
            'sale_starts_at' => ['nullable', 'date'],
            'sale_ends_at' => ['nullable', 'date', 'after_or_equal:sale_starts_at'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * Zvolený druh (kind + open_to_public) musí patriť medzi možnosti, ktoré
     * daný používateľ smie nastaviť — rovnaká policy ako vo výbere vo fronte.
     *
     * Navyše sa tu porovnáva rozsah na objednávku. Oba kusy logiky sú v
     * `after()`, nie v `rules()`: obe polia sú `sometimes`, takže pri čiastočnej
     * úprave (napr. len prepnutie `is_active`) v požiadavke vôbec nie sú
     * a pravidlo `gte:min_per_order` by nemalo čo porovnať.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateOrderRange($validator);

            if (! $this->filled('kind')) {
                return;
            }

            $kind = TicketTypeKind::tryFrom((string) $this->input('kind'));
            if ($kind === null) {
                return; // neplatnú hodnotu už zachytí Rule::enum vyššie
            }

            $option = TicketTypeKindOption::fromModel($kind, $this->boolean('open_to_public'));

            if (! in_array($option, TicketTypeKindOption::allowedForUser($this->user()), true)) {
                $validator->errors()->add('kind', __('tickets.errors.kind_not_allowed'));
            }
        });
    }

    /**
     * Horná hranica objednávky nesmie byť pod dolnou — taký typ by sa nedal
     * objednať vôbec: stepper vo fronte by sa nedostal nad `max_per_order`
     * a repozitár by každú objednávku zhodil na `min_per_order`.
     */
    private function validateOrderRange(Validator $validator): void
    {
        if (! $this->filled('min_per_order') || ! $this->filled('max_per_order')) {
            return;
        }

        if ((int) $this->input('max_per_order') < (int) $this->input('min_per_order')) {
            $validator->errors()->add('max_per_order', __('tickets.errors.max_below_min'));
        }
    }
}
