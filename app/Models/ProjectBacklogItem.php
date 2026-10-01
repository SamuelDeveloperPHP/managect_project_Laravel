<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectBacklogItem extends Model
{
    use AuditsChanges, BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'project_id', 'code', 'epic', 'title', 'description', 'priority', 'status', 'release', 'points'];

    protected static function booted(): void
    {
        $validateProjectCompany = static function (self $item): void {
            $project = Project::withoutGlobalScopes()->findOrFail($item->project_id);

            if ($project->company_id !== $item->company_id) {
                throw new \LogicException('O item do backlog deve pertencer à mesma empresa do projeto.');
            }
        };

        static::creating($validateProjectCompany);
        static::updating($validateProjectCompany);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
