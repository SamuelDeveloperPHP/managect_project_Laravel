<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectBacklogItem extends Model
{
    use AuditsChanges, BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'project_id', 'project_backlog_id', 'code', 'epic', 'title', 'description', 'priority', 'status', 'release', 'points'];

    protected static function booted(): void
    {
        $validateProjectCompany = static function (self $item): void {
            $project = Project::withoutGlobalScopes()->findOrFail($item->project_id);

            if ($project->company_id !== $item->company_id) {
                throw new \LogicException('O item do backlog deve pertencer à mesma empresa do projeto.');
            }

            $backlog = ProjectBacklog::withoutGlobalScopes()->findOrFail($item->project_backlog_id);
            if ((int) $backlog->project_id !== (int) $item->project_id || (int) $backlog->company_id !== (int) $item->company_id) {
                throw new \LogicException('O item deve pertencer ao backlog e ao projeto correspondentes.');
            }
        };

        static::creating($validateProjectCompany);
        static::updating($validateProjectCompany);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function backlog(): BelongsTo
    {
        return $this->belongsTo(ProjectBacklog::class, 'project_backlog_id');
    }

    public function ganttTasks(): HasMany
    {
        return $this->hasMany(GanttTask::class, 'project_backlog_item_id');
    }
}
