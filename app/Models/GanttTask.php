<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GanttTask extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'phalcon_id', 'company_id', 'project_id', 'project_backlog_item_id', 'code', 'name', 'description', 'level', 'status', 'progress',
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

    public function assignments(): HasMany
    {
        return $this->hasMany(GanttTaskAssignment::class);
    }
}
