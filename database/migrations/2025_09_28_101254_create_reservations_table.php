<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id('id_reservation');
            $table->foreignId('id_utilisateur')->constrained('utilisateurs','id_utilisateur');
            $table->foreignId('id_logement')->constrained('logements','id_logement');
            $table->dateTime('date_debut');
            $table->dateTime('date_fin');
            $table->unsignedInteger('nb_personnes');
            $table->enum('statut', ['en_attente','confirme','annule']);
            $table->decimal('montant_total', 12, 2);
            $table->timestamps();
        });

        // Add check constraint using raw SQL
        // (SQLite, used by the PHPUnit suite, does not support ALTER TABLE ... ADD CONSTRAINT)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE reservations ADD CONSTRAINT check_date_fin_after_debut CHECK (date_fin > date_debut)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
