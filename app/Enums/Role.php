<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    public function rank(): int
    {
        return match ($this) {
            self::Owner => 4,
            self::Admin => 3,
            self::Member => 2,
            self::Viewer => 1,
        };
    }

    /**
     * The permission matrix.
     *
     * @return list<Permission>
     */
    public function permissions(): array
    {
        $viewerPermissions = [Permission::MembersView, Permission::BoardView];

        $memberPermissions = [
            ...$viewerPermissions,
            Permission::BoardCreate,
            Permission::BoardUpdate,
            Permission::ColumnManage,
            Permission::CardManage,
            Permission::CommentCreate,
        ];

        return match ($this) {
            self::Owner, self::Admin => Permission::cases(),
            self::Member => $memberPermissions,
            self::Viewer => $viewerPermissions,
        };
    }

    public function can(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function canManage(Role $target): bool
    {
        return $this->rank() > $target->rank();
    }

    public function canAssign(Role $role): bool
    {
        return $role !== self::Owner && $this->rank() > $role->rank();
    }

    /**
     * @return list<Role>
     */
    public function assignableRoles(): array
    {
        return array_values(array_filter(self::cases(), fn (Role $role) => $this->canAssign($role)));
    }
}
