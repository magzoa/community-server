<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = [
        'main_title',
        'secondary_title',
        'daily_phrase',
    ];

    /**
     * Devuelve la única fila de configuración (la crea vacía si no existe).
     */
    public static function current(): self
    {
        return static::firstOrCreate([]);
    }
}
