<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\AuditsChanges;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    public const PLATFORM_MASTER_EMAIL = 'admin.master@phalcon.local';

    public const PLATFORM_MASTER_COMPANY_ID = 2;

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
        $secondary = $this->role === 'admin' && $notification instanceof ResetPassword
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
        $role = $this->role ?: 'user';

        if ($role === 'master' || $this->isPlatformMasterIdentity()) {
            return $this->isPlatformMasterIdentity() && in_array('master', $roles, true);
        }

        return in_array($role, $roles, true);
    }

    /**
     * E-mail da ÚNICA conta Master da plataforma. O padrão serve para desenvolvimento; em produção defina
     * PLATFORM_MASTER_EMAIL com um e-mail real (a recuperação de senha precisa conseguir chegar à caixa de entrada).
     */
    public static function platformMasterEmail(): string
    {
        return mb_strtolower(trim((string) config('platform.master_email', self::PLATFORM_MASTER_EMAIL)));
    }

    public function isPlatformMasterIdentity(): bool
    {
        return mb_strtolower((string) $this->email) === self::platformMasterEmail();
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->isPlatformMasterIdentity()) {
                if ($user->role !== 'master') {
                    throw new \LogicException('A conta da plataforma deve manter o papel Master.');
                }

                $user->email = self::platformMasterEmail();
                $user->company_id = self::PLATFORM_MASTER_COMPANY_ID;
                $user->permissions = [];

                return;
            }

            if ($user->role === 'master') {
                throw new \LogicException('O papel Master é reservado à conta '.self::platformMasterEmail().'.');
            }
        });
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('master', 'admin')) {
            return true;
        }

        return (bool) (($this->permissions ?? [])[$permission] ?? false);
    }
}
