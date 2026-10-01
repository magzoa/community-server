<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Apartado "Meetup destacado" del home (englobador de eventos).
     */
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->string('meetup_title')->nullable();
            $table->string('meetup_subtitle')->nullable();
            $table->text('meetup_description')->nullable();
            $table->string('meetup_url')->nullable();
            $table->string('meetup_image')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn([
                'meetup_title',
                'meetup_subtitle',
                'meetup_description',
                'meetup_url',
                'meetup_image',
            ]);
        });
    }
};
