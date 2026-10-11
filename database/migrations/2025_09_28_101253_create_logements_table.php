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
        Schema::create('logements', function (Blueprint $table) {
            $table->id('id_logement');
            $table->foreignId('id_agence')->constrained('agences','id_agence');
            $table->string('titre', 180);
            $table->text('description')->nullable();
            $table->enum('type', ['hotel','maison','studio']);
            $table->unsignedInteger('capacite');
            $table->string('adresse', 255)->nullable();
            $table->string('photo_url', 255)->nullable();
            $table->enum('statut', ['actif','inactif']);

            // Tarification intégrée
            $table->decimal('tarif_amount', 12, 2);
            $table->enum('tarif_unit', ['heure','jour','mois']);
            $table->char('devise', 3)->default('MAD');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logements');
    }
};
