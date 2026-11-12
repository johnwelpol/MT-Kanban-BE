<?php

namespace App\Http\Requests\Boards;

use App\Enums\Permission;
use App\Models\Board;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows(Permission::CardManage->value);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Board $board */
        $board = $this->route('board');

        return [
            'column_id' => ['required', 'string', Rule::exists('board_columns', 'id')
                ->where('tenant_id', tenant()->getTenantKey())
                ->where('board_id', $board->id)],
            'title' => ['required', 'string', 'min:1', 'max:200'],
        ];
    }
}
