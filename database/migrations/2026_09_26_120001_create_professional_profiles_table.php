<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de perfiles profesionales (CEO, Desarrollador...).
     * Relación 1:N: un miembro tiene un perfil profesional.
     */
    public function up(): void
    {
        Schema::create('professional_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 7)->nullable(); // #RRGGBB
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_profiles');
    }
};
