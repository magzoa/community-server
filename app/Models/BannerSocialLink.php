<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BannerSocialLink extends Model
{
    /**
     * Tipos de red permitidos.
     *
     * @var list<string>
     */
    public const TYPES = [
        'instagram',
        'linkedin',
        'youtube',
        'meetup',
        'twitter',
        'website',
        'other',
    ];

    protected $fillable = [
        'type',
        'url',
        'label',
        'icon',
        'image_url',
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
     * Disco de archivos (reusa el de miembros; cambiar a s3 vía .env).
     */
    public static function disk(): string
    {
        return env('MEMBER_FILES_DISK', 'public');
    }

    /**
     * Carpeta de la imagen de esta red: banner/social/<id>-<slug>.
     */
    public function imageFolder(): string
    {
        $slug = Str::slug($this->label ?: $this->type) ?: 'red';

        return "banner/social/{$this->id}-{$slug}";
    }

    /**
     * Al eliminar la red, borra su carpeta de imagen del disco.
     */
    protected static function booted(): void
    {
        static::deleting(function (BannerSocialLink $link) {
            Storage::disk(self::disk())->deleteDirectory($link->imageFolder());
        });
    }
}
