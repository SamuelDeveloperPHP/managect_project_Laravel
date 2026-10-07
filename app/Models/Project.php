<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use AuditsChanges, BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = ['name', 'code', 'description', 'status', 'client', 'priority', 'start_date', 'deadline', 'budget'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'deadline' => 'date', 'budget' => 'decimal:2'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function backlogItems(): HasMany
    {
        return $this->hasMany(ProjectBacklogItem::class);
    }

    public function backlogs(): HasMany
    {
        return $this->hasMany(ProjectBacklog::class)->orderBy('name');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(GanttTask::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')->withPivot('created_at');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class);
    }
}
