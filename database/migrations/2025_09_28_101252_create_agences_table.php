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
        Schema::create('agences', function (Blueprint $table) {
            $table->id('id_agence');
            $table->string('nom', 150);
            $table->string('adresse', 255)->nullable();
            $table->string('telephone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('description')->nullable();
            $table->decimal('score_classement', 4, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agences');
    }
};
