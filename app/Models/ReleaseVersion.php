<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReleaseVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'branch_name', 'commit_sha', 'commit_message', 'implemented_notes', 'fixed_notes', 'updated_notes', 'executed_by', 'released_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'implemented_notes' => 'array',
            'fixed_notes' => 'array',
            'updated_notes' => 'array',
            'released_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
