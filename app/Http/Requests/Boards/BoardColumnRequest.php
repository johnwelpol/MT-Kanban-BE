<?php

namespace App\Http\Requests\Boards;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Creating and renaming a column take the same input.
 */
class BoardColumnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Permission::ColumnManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:60'],
        ];
    }
}
