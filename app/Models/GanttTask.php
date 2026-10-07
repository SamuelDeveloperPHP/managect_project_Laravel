<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GanttTask extends Model
{
    use BelongsToCompany;

    protected static function booted(): void
    {
        static::saving(function (self $task): void {
            if (! $task->project_id && ! $task->project_backlog_id) {
                return;
            }
            if (! $task->project_backlog_id) {
                throw new \LogicException('Toda tarefa vinculada a um projeto deve pertencer a um backlog.');
            }
            // Quem grava muitas tarefas de uma vez (editor do Gantt) já carrega o backlog e o injeta na relação.
            $backlog = $task->relationLoaded('backlog') && (int) $task->getRelation('backlog')?->id === (int) $task->project_backlog_id
                ? $task->getRelation('backlog')
                : ProjectBacklog::withoutGlobalScopes()->findOrFail($task->project_backlog_id);
            if ((int) $backlog->project_id !== (int) $task->project_id || (int) $backlog->company_id !== (int) $task->company_id) {
                throw new \LogicException('A tarefa Gantt deve pertencer ao backlog e ao projeto correspondentes.');
            }
        });
    }

    protected $fillable = [
        'phalcon_id', 'company_id', 'project_id', 'project_backlog_id', 'project_backlog_item_id', 'code', 'name', 'description', 'level', 'status', 'progress',
        'start_at', 'end_at', 'duration', 'depends', 'sort_order', 'collapsed', 'start_is_milestone',
        'end_is_milestone', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime', 'end_at' => 'datetime', 'collapsed' => 'boolean',
            'start_is_milestone' => 'boolean', 'end_is_milestone' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function backlogItem(): BelongsTo
    {
        return $this->belongsTo(ProjectBacklogItem::class, 'project_backlog_item_id');
    }

    public function backlog(): BelongsTo
    {
        return $this->belongsTo(ProjectBacklog::class, 'project_backlog_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(GanttTaskAssignment::class);
    }
}
