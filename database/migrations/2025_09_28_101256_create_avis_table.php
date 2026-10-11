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
        Schema::create('avis', function (Blueprint $table) {
            $table->id('id_avis');
            $table->foreignId('id_utilisateur')->constrained('utilisateurs','id_utilisateur');
            $table->foreignId('id_agence')->constrained('agences','id_agence');
            $table->tinyInteger('note');
            $table->text('commentaire')->nullable();
            $table->date('date_avis');
            $table->timestamps();

            $table->unique(['id_utilisateur', 'id_agence', 'date_avis']);
        });

        // Add check constraint using raw SQL
        // (SQLite, used by the PHPUnit suite, does not support ALTER TABLE ... ADD CONSTRAINT)
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE avis ADD CONSTRAINT check_note_range CHECK (note BETWEEN 1 AND 5)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avis');
    }
};
