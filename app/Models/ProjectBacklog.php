<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectBacklog extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'project_id', 'code', 'name', 'description', 'status'];

    protected static function booted(): void
    {
        static::creating(function (self $backlog): void {
            $project = Project::withoutGlobalScopes()->findOrFail($backlog->project_id);
            if ((int) $project->company_id !== (int) $backlog->company_id) {
                throw new \LogicException('O backlog deve pertencer à mesma empresa do projeto.');
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectBacklogItem::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(GanttTask::class);
    }
}
