<?php

namespace Tests\Unit\Enums;

use App\Enums\Permission;
use App\Enums\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RoleTest extends TestCase
{
    /**
     * The 00-overview §5 matrix, written out independently of Role::permissions().
     */
    private const MATRIX = [
        'workspace.update' => ['owner' => true, 'admin' => true, 'member' => false, 'viewer' => false],
        'members.view' => ['owner' => true, 'admin' => true, 'member' => true, 'viewer' => true],
        'members.manage' => ['owner' => true, 'admin' => true, 'member' => false, 'viewer' => false],
        'invitations.manage' => ['owner' => true, 'admin' => true, 'member' => false, 'viewer' => false],
        'board.view' => ['owner' => true, 'admin' => true, 'member' => true, 'viewer' => true],
        'board.create' => ['owner' => true, 'admin' => true, 'member' => true, 'viewer' => false],
        'board.update' => ['owner' => true, 'admin' => true, 'member' => true, 'viewer' => false],
        'board.delete' => ['owner' => true, 'admin' => true, 'member' => false, 'viewer' => false],
        'column.manage' => ['owner' => true, 'admin' => true, 'member' => true, 'viewer' => false],
        'card.manage' => ['owner' => true, 'admin' => true, 'member' => true, 'viewer' => false],
        'comment.create' => ['owner' => true, 'admin' => true, 'member' => true, 'viewer' => false],
        'comment.moderate' => ['owner' => true, 'admin' => true, 'member' => false, 'viewer' => false],
    ];

    #[DataProvider('rolePermissionMatrix')]
    public function test_role_grants_exactly_the_permissions_in_the_matrix(Role $role, Permission $permission, bool $expected): void
    {
        $this->assertSame($expected, $role->can($permission));
    }

    /**
     * @return iterable<string, array{0: Role, 1: Permission, 2: bool}>
     */
    public static function rolePermissionMatrix(): iterable
    {
        foreach (Permission::cases() as $permission) {
            foreach (Role::cases() as $role) {
                yield "{$role->value} {$permission->value}" => [$role, $permission, self::MATRIX[$permission->value][$role->value]];
            }
        }
    }

    /**
     * @param  list<Role>  $expected
     */
    #[DataProvider('assignableRolesByRole')]
    public function test_assignable_roles_are_strictly_lower_and_never_owner(Role $role, array $expected): void
    {
        $this->assertSame($expected, $role->assignableRoles());
    }

    /**
     * @return array<string, array{0: Role, 1: list<Role>}>
     */
    public static function assignableRolesByRole(): array
    {
        return [
            'owner' => [Role::Owner, [Role::Admin, Role::Member, Role::Viewer]],
            'admin' => [Role::Admin, [Role::Member, Role::Viewer]],
            'member' => [Role::Member, [Role::Viewer]],
            'viewer' => [Role::Viewer, []],
        ];
    }

    public function test_nobody_can_assign_owner(): void
    {
        $this->assertFalse(Role::Owner->canAssign(Role::Owner));
    }

    public function test_can_manage_only_strictly_lower_ranks(): void
    {
        $this->assertTrue(Role::Owner->canManage(Role::Admin));
        $this->assertTrue(Role::Admin->canManage(Role::Member));
        $this->assertFalse(Role::Admin->canManage(Role::Admin));
        $this->assertFalse(Role::Admin->canManage(Role::Owner));
        $this->assertFalse(Role::Viewer->canManage(Role::Viewer));
    }
}
