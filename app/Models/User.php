<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use App\Models\Concerns\AuditsChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use AuditsChanges, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'company_id',
        'email',
        'password',
        'role',
        'permissions',
        'is_active',
        'cpf',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Password recovery for a company administrator goes to the login e-mail and,
     * when configured, to the company's distinct secondary recovery e-mail.
     *
     * @return string|list<string>
     */
    public function routeNotificationForMail(mixed $notification = null): string|array
    {
        $secondary = $this->role === 'admin' && $notification instanceof \Illuminate\Auth\Notifications\ResetPassword
            ? $this->company?->secondary_recovery_email
            : null;

        if ($secondary && mb_strtolower($secondary) !== mb_strtolower($this->email)) {
            return [$this->email, $secondary];
        }

        return $this->email;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role ?: 'user', $roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('master', 'admin')) {
            return true;
        }

        return (bool) (($this->permissions ?? [])[$permission] ?? false);
    }
}
