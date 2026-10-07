<?php

namespace App\Http\Controllers;

use App\Models\Project;

abstract class Controller
{
    /** 403 se a pessoa não pode alterar ESTE projeto (ver Project::canBeManagedBy). */
    protected function authorizeProjectManagement(Project $project): void
    {
        abort_unless(
            $project->canBeManagedBy(request()->user()),
            403,
            'Você não participa deste projeto. Peça ao administrador para incluí-lo como membro ou líder.',
        );
    }
}
