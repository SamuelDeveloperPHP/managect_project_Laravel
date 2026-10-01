<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use AuditsChanges, BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = ['name', 'code', 'description', 'status', 'client', 'priority', 'start_date', 'deadline', 'budget'];

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

    public function tasks(): HasMany
    {
        return $this->hasMany(GanttTask::class);
    }
}
