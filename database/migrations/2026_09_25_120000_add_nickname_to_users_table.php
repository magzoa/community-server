<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el identificador único "nickname" a la tabla users.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Identificador único obligatorio
            $table->string('nickname')->unique()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nickname']);
            $table->dropColumn('nickname');
        });
    }
};
