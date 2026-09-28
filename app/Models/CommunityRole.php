<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CommunityRole extends Model
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
     * Miembros con este rol de comunidad (N:N).
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_community_role');
    }
}
