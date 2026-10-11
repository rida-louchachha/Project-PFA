<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id('id_conversation');
            $table->foreignId('id_utilisateur')->constrained('utilisateurs', 'id_utilisateur')->cascadeOnDelete();
            // Null = general support conversation (not tied to a booking)
            $table->foreignId('id_reservation')->nullable()->constrained('reservations', 'id_reservation')->cascadeOnDelete();
            $table->string('channel', 20)->default('in_app');

            // Human handoff state
            $table->enum('statut', ['active', 'handoff', 'closed'])->default('active');
            $table->string('handoff_reason', 255)->nullable();
            $table->timestamp('handoff_requested_at')->nullable();
            $table->timestamp('handoff_resolved_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('utilisateurs', 'id_utilisateur')->nullOnDelete();

            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            // One conversation per customer and reservation
            $table->unique(['id_utilisateur', 'id_reservation']);
            $table->index(['statut', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};
