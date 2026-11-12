<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceResource;

class PublicWorkspaceController extends Controller
{
    public function __invoke(): WorkspaceResource
    {
        return new WorkspaceResource(tenant());
    }
}
