<?php

namespace App\Http\Controllers;

use App\Models\ReleaseVersion;
use Inertia\Inertia;
use Inertia\Response;

class ReleaseVersionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Company/Versions', [
            'versions' => ReleaseVersion::query()->orderByDesc('released_at')->orderByDesc('id')->limit(100)->get([
                'id', 'branch_name', 'commit_sha', 'commit_message', 'implemented_notes', 'fixed_notes', 'updated_notes', 'executed_by', 'released_at',
            ])->map(function (ReleaseVersion $version): array {
                $notes = [
                    'implemented' => array_values(array_filter((array) $version->implemented_notes, fn ($note) => is_string($note) && trim($note) !== '')),
                    'fixed' => array_values(array_filter((array) $version->fixed_notes, fn ($note) => is_string($note) && trim($note) !== '')),
                    'updated' => array_values(array_filter((array) $version->updated_notes, fn ($note) => is_string($note) && trim($note) !== '')),
                ];
                if ($notes['implemented'] === [] && $notes['fixed'] === [] && $notes['updated'] === []) {
                    $notes['updated'] = [$version->commit_message ?: 'Publicação de versão registrada.'];
                }

                return [
                    'id' => $version->id,
                    'branch_name' => $version->branch_name,
                    'commit_sha' => $version->commit_sha,
                    'commit_message' => $version->commit_message ?: 'Atualização do sistema',
                    'executed_by' => $version->executed_by,
                    'released_at' => $version->released_at,
                    'notes' => $notes,
                ];
            })->values(),
        ]);
    }
}
