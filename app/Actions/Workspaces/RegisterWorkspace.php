<?php

namespace App\Actions\Workspaces;

use App\Actions\Boards\CreateBoard;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\LoginHandoffs;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisterWorkspace
{
    public function __construct(private LoginHandoffs $handoffs, private CreateBoard $createBoard) {}

    /**
     * Create a workspace owned by a new or existing user, and a handoff code to sign them in on it.
     *
     * @param  array{name?: string, email: string, password: string, workspace_name: string, workspace_slug: string}  $data
     * @return array{0: Tenant, 1: string} the tenant and the plain handoff code
     */
    public function handle(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $user = $this->resolveOwner($data);

            try {
                /**
                 * @var Tenant $tenant
                 */
                $tenant = Tenant::create([
                    'slug' => $data['workspace_slug'],
                    'name' => $data['workspace_name'],
                    'status' => 'active',
                ]);
            } catch (UniqueConstraintViolationException) {
                // Lost a race with another registration after validation passed.
                throw ValidationException::withMessages(['workspace_slug' => 'This URL is already taken.']);
            }

            $tenant->users()->attach($user, ['role' => Role::Owner]);

            $tenant->run(fn () => $this->createBoard->handle($user, 'Getting started'));

            return [$tenant, $this->handoffs->create($user, $tenant)];
        });
    }

    /**
     * @param  array{name?: string, email: string, password: string}  $data
     */
    private function resolveOwner(array $data): User
    {
        $existingUser = User::where('email', $data['email'])->first();

        if ($existingUser === null) {
            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
        }

        if (! Hash::check($data['password'], $existingUser->password)) {
            throw ValidationException::withMessages([
                'password' => "This email already has an account. Enter that account's password to create another workspace.",
            ]);
        }

        return $existingUser;
    }
}
