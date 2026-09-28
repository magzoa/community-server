<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vincula members con los catálogos:
     * - professional_profile_id (FK, uno por miembro; null al borrar el perfil).
     * - tabla pivote member_community_role (N:N con roles de comunidad).
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('professional_profile_id')
                ->nullable()
                ->after('job_title')
                ->constrained('professional_profiles')
                ->nullOnDelete();
        });

        Schema::create('member_community_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('community_role_id')->constrained()->cascadeOnDelete();
            $table->unique(['member_id', 'community_role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_community_role');

        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['professional_profile_id']);
            $table->dropColumn('professional_profile_id');
        });
    }
};
