<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Traits\HasRoles;


//class User extends Authenticatable
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'persona_id',
        'password',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole(['admin', 'secretario', 'facilitador', 'lider']);
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }

    public function isRestrictedPanelUser(): bool
    {
        return $this->hasRole(['facilitador', 'lider']) && ! $this->hasRole('admin');
    }

    public function hasCombinedFacilitadorLiderAccess(): bool
    {
        return $this->hasAllRoles(['facilitador', 'lider']) && ! $this->hasRole('admin');
    }

    public function isSoloLider(): bool
    {
        return $this->hasRole('lider') && ! $this->hasRole(['admin', 'facilitador']);
    }

    public function canManageLeadershipArea(): bool
    {
        return $this->hasRole(['admin', 'lider']);
    }

    public function metagruposLiderados(): Builder
    {
        if (! $this->persona_id) {
            return Metagrupo::query()->whereRaw('1 = 0');
        }

        return Metagrupo::query()
            ->where('lider_persona_id', $this->persona_id)
            ->withSummaryColumns();
    }

    public function gruposMinisterialesLiderados(): Builder
    {
        if (! $this->persona) {
            return Grupo::query()->whereRaw('1 = 0');
        }

        return $this->persona->gruposMinisterialesLiderados();
    }

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
        ];
    }
}
