<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property Role $role
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
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
        'google_id',
        'role',
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
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    /**
     * @return BelongsToMany<Planning, $this>
     */
    public function plannings(): BelongsToMany
    {
        return $this->belongsToMany(Planning::class);
    }

    /**
     * @return HasMany<ExternalTaskComment, $this>
     */
    public function externalTaskComments(): HasMany
    {
        return $this->hasMany(ExternalTaskComment::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::ADMIN;
    }

    public function isAlgemeenMedewerker(): bool
    {
        return $this->role === Role::ALGEMEEN_MEDEWERKER;
    }

    public function isGebruiker(): bool
    {
        return $this->role === Role::GEBRUIKER;
    }

    public function canManagePlannings(): bool
    {
        return $this->role === Role::ADMIN || $this->role === Role::FACILITIES_COORDINATOR;
    }

    public function canTriggerPhotoWorkflow(): bool
    {
        return $this->role === Role::ADMIN || $this->role === Role::FACILITIES_COORDINATOR;
    }

    public function canExecutePlannings(): bool
    {
        return $this->isAdmin() || $this->isAlgemeenMedewerker();
    }

    public function canViewBacklog(): bool
    {
        return true; // Alle gebruikers kunnen backlog bekijken
    }

    public function canCreateTasks(): bool
    {
        return true; // Alle gebruikers kunnen taken aanmaken
    }
}
