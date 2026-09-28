<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfessionalProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Miembros con este perfil profesional (1:N).
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
