<?php

namespace App\Http\Requests;

use App\Enums\CanalRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CanalMemberRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(array_column(CanalRole::cases(), 'value'))],
        ];
    }

    public function role(): CanalRole
    {
        return CanalRole::from($this->validated('role'));
    }
}
