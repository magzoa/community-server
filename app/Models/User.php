<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Atributos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'nickname',
        'email',
        'password',
        'oauth_id',
        'oauth_type',
    ];

    /**
     * Atributos ocultos en la serialización.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Casts de atributos.
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

    /**
     * Limpieza al eliminar el usuario: borra tokens y roles.
     * El member y sus social_links caen por cascada de la BD (FK).
     */
    protected static function booted(): void
    {
        static::deleting(function (User $user) {
            $user->tokens()->delete();   // tokens de Sanctum (polimórfico, sin FK)
            $user->syncRoles([]);        // roles de Spatie (tabla pivote polimórfica)
        });
    }

    /**
     * Perfil de miembro asociado (1:1).
     */
    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }
}
