<?php

namespace App\Http\Requests\Central;

use App\Models\User;
use App\Rules\AvailableWorkspaceSlug;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterWorkspaceRequest extends FormRequest
{
    /**
     * Registration is public; the internal key middleware already restricts callers.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An existing email only needs that account's password (checked in RegisterWorkspace);
     * a new email also needs a name and a confirmed, strong password.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isNewAccount = ! User::where('email', (string) $this->input('email'))->exists();

        return [
            'workspace_name' => ['required', 'string', 'min:2', 'max:60'],
            'workspace_slug' => ['required', 'string', new AvailableWorkspaceSlug],
            'email' => ['required', 'string', 'email', 'max:255'],
            'name' => Rule::when($isNewAccount, ['required', 'string', 'min:2', 'max:60'], ['exclude']),
            'password' => ['required', 'string', ...($isNewAccount ? ['confirmed', Password::defaults()] : [])],
        ];
    }
}
