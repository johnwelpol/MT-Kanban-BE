<?php

namespace App\Http\Requests\Boards;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class MoveColumnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Permission::ColumnManage->value);
    }

    /**
     * Out-of-range positions are clamped by MoveColumn, so only negatives are rejected.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'position' => ['required', 'integer', 'min:0'],
        ];
    }
}
