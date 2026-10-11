<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ownership model: an agency account (utilisateurs.role = 'agence') owns at most
 * one agence row. Agency-scoped data (reservations, AI conversations) is only
 * exposed to the account linked here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->foreignId('id_utilisateur')
                ->nullable()
                ->unique()
                ->after('id_agence')
                ->constrained('utilisateurs', 'id_utilisateur')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_utilisateur');
        });
    }
};
