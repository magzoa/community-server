<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de redes sociales del miembro (1:N).
     * Se permiten duplicados de "type" (ej. dos webs).
     */
    public function up(): void
    {
        Schema::create('member_social_links', function (Blueprint $table) {
            $table->id();

            $table->foreignId('member_id')
                ->constrained()
                ->cascadeOnDelete();

            // Tipo de red: github | linkedin | twitter | website | instagram | youtube | other
            $table->string('type');
            $table->string('url');
            $table->string('label')->nullable();   // texto a mostrar (ej. "@usuario")
            $table->string('icon')->nullable();     // ícono mdi (ej. "mdi-github")
            $table->string('image_url')->nullable(); // imagen personalizada del ícono
            $table->integer('sort_order')->default(0); // orden en la UI

            $table->timestamps();

            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_social_links');
    }
};
