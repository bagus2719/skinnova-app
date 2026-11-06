<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'phone',
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
     * Control Filament Admin Panel access.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) ($this->is_active ?? true);
    }

    /**
     * Role checking helpers
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function isSales(): bool
    {
        return $this->isAdmin() || $this->role === 'sales';
    }

    public function isPurchasing(): bool
    {
        return $this->isAdmin() || $this->role === 'purchasing';
    }

    public function isInventory(): bool
    {
        return $this->isAdmin() || $this->role === 'inventory';
    }

    public function isProduction(): bool
    {
        return $this->isAdmin() || $this->role === 'production';
    }

    public function isFinance(): bool
    {
        return $this->isAdmin() || $this->role === 'finance';
    }

    public function isHr(): bool
    {
        return $this->isAdmin() || $this->role === 'hr';
    }

    public function hasAnyRole(array $roles): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($this->role, $roles);
    }
}
