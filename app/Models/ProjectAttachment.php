<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAttachment extends Model
{
    protected $fillable = ['project_id', 'uploaded_by', 'original_name', 'stored_path', 'mime_type', 'size_bytes'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
