<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Redes del banner del home (Instagram, LinkedIn, YouTube, Meetup...).
     * Gestionables desde /admin. Icono con prioridad; imagen guardada aparte.
     */
    public function up(): void
    {
        Schema::create('banner_social_links', function (Blueprint $table) {
            $table->id();
            // instagram|linkedin|youtube|meetup|twitter|website|other
            $table->string('type');
            $table->string('url');
            $table->string('label')->nullable();
            $table->string('icon')->nullable();       // ícono mdi (prioritario)
            $table->string('image_url')->nullable();  // imagen subida (uso futuro)
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_social_links');
    }
};
