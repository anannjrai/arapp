<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_PREPARER = 'preparer';

    public const ROLE_REVIEWER = 'reviewer';

    public const ROLE_EXPORTER = 'exporter';

    public const ROLE_VIEWER = 'viewer';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function roles(): array
    {
        return [
            self::ROLE_ADMIN => 'Administrator',
            self::ROLE_PREPARER => 'Preparer',
            self::ROLE_REVIEWER => 'Reviewer',
            self::ROLE_EXPORTER => 'Exporter',
            self::ROLE_VIEWER => 'Viewer',
        ];
    }

    /**
     * @param array<int, string> $roles
     */
    public function hasRole(array $roles): bool
    {
        return $this->role === self::ROLE_ADMIN || in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function roleLabel(): string
    {
        return self::roles()[$this->role] ?? ucfirst((string) $this->role);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function uploadedPaymentBatches(): HasMany
    {
        return $this->hasMany(PaymentBatch::class, 'uploaded_by');
    }
}
