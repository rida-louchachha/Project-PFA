<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id('id_paiement');
            $table->foreignId('id_reservation')->constrained('reservations','id_reservation')->unique();
            $table->enum('mode', ['especes','terminal','en_ligne']);
            $table->decimal('montant', 12, 2);
            $table->dateTime('date_paiement');
            $table->enum('statut', ['en_attente','regle','echoue']);
            $table->string('reference', 120)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
