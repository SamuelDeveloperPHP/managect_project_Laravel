<?php

namespace App\Models;

use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use AuditsChanges, HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'document_type', 'document_number', 'is_active', 'cnpj', 'domain', 'zip_code', 'street',
        'number', 'complement', 'neighborhood', 'city', 'state', 'logo_path', 'contact_name', 'contact_email',
        'contact_whatsapp', 'admin_recovery_email', 'secondary_recovery_email',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CompanyDocument::class);
    }
}
