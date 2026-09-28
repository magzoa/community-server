<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea la tabla de perfiles de miembro (relación 1:1 con users).
     */
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();

            // Relación 1:1 con el usuario dueño del perfil
            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            // Datos de contacto / perfil
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('country')->nullable();
            $table->string('city')->nullable();
            $table->text('bio')->nullable();
            $table->string('avatar_url')->nullable();
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();

            // Estado de aprobación
            $table->string('status')->default('pending'); // pending | approved | rejected
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Personalización de perfil (colores hex #RRGGBB)
            $table->string('primary_color', 7)->nullable();
            $table->string('secondary_color', 7)->nullable();
            $table->string('text_color', 7)->nullable();
            $table->string('background_color', 7)->nullable();
            $table->string('theme')->nullable(); // light | dark | auto
            $table->string('banner_url')->nullable();

            $table->timestamps();

            // Índice para filtrar por estado (listado admin)
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
