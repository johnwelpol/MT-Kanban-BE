<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CardComment;
use App\Models\User;

class CardCommentPolicy
{
    /**
     * Authors can delete their own comments; moderators (owner/admin) can delete any.
     */
    public function delete(User $user, CardComment $comment): bool
    {
        return $comment->user_id === $user->id || ($user->currentRole()?->can(Permission::CommentModerate) ?? false);
    }
}
