<?php

namespace Tests\Feature\Providers;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    public function test_permission_gate_follows_the_current_role(): void
    {
        $user = new User;
        $user->setCurrentMembership(new Membership(['role' => Role::Member]));

        $this->assertTrue(Gate::forUser($user)->allows(Permission::CardManage->value));
        $this->assertFalse(Gate::forUser($user)->allows(Permission::BoardDelete->value));
    }

    public function test_permission_gate_denies_without_a_current_membership(): void
    {
        $this->assertFalse(Gate::forUser(new User)->allows(Permission::BoardView->value));
    }
}
