<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberSocialLink extends Model
{
    use HasFactory;

    /**
     * Tipos de red social permitidos.
     *
     * @var list<string>
     */
    public const TYPES = [
        'github',
        'linkedin',
        'twitter',
        'website',
        'instagram',
        'youtube',
        'other',
    ];

    /**
     * Atributos asignables masivamente.
     *
     * @var list<string>
     */
    protected $fillable = [
        'member_id',
        'type',
        'url',
        'label',
        'icon',
        'image_url',
        'sort_order',
    ];

    /**
     * Miembro dueño de la red social.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
