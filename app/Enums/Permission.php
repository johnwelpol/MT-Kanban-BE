<?php

namespace App\Enums;

enum Permission: string
{
    case WorkspaceUpdate = 'workspace.update';
    case MembersView = 'members.view';
    case MembersManage = 'members.manage';
    case InvitationsManage = 'invitations.manage';
    case BoardView = 'board.view';
    case BoardCreate = 'board.create';
    case BoardUpdate = 'board.update';
    case BoardDelete = 'board.delete';
    case ColumnManage = 'column.manage';
    case CardManage = 'card.manage';
    case CommentCreate = 'comment.create';
    case CommentModerate = 'comment.moderate';
}
