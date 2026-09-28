<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    /**
     * Estados posibles del miembro.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Atributos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'phone',
        'contact_email',
        'country',
        'city',
        'bio',
        'avatar_url',
        'company',
        'job_title',
        'professional_profile_id',
        'status',
        'approved_by',
        'approved_at',
        'primary_color',
        'secondary_color',
        'text_color',
        'background_color',
        'theme',
        'banner_url',
    ];

    /**
     * Casts de atributos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Usuario dueño del perfil.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Admin que aprobó/rechazó al miembro.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Redes sociales del miembro (1:N).
     */
    public function socialLinks(): HasMany
    {
        return $this->hasMany(MemberSocialLink::class);
    }

    /**
     * Perfil profesional (1:1 con catálogo).
     */
    public function professionalProfile(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class);
    }

    /**
     * Roles de comunidad (N:N con catálogo).
     */
    public function communityRoles(): BelongsToMany
    {
        return $this->belongsToMany(CommunityRole::class, 'member_community_role');
    }
}
