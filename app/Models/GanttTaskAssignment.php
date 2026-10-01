<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GanttTaskAssignment extends Model
{
    protected $fillable = ['phalcon_id', 'gantt_task_id', 'company_id', 'user_id', 'role', 'effort'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(GanttTask::class, 'gantt_task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
