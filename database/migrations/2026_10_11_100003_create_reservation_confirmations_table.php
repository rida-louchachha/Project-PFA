<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer arrival intention, kept separate from reservations.statut on purpose:
 * the AI records what the customer said, it never changes the booking/payment state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_confirmations', function (Blueprint $table) {
            $table->id('id_confirmation');
            $table->foreignId('id_reservation')->unique()->constrained('reservations', 'id_reservation')->cascadeOnDelete();
            $table->enum('intention', [
                'pending',
                'confirmed',
                'declined',
                'uncertain',
                'modification_requested',
                'human_assistance_required',
            ])->default('pending');
            $table->decimal('confidence', 3, 2)->nullable();
            $table->enum('source', ['system', 'ai_classifier', 'agent'])->default('system');
            // Customer message the intention was detected in
            $table->foreignId('id_message')->nullable()->constrained('ai_messages', 'id_message')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index('intention');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_confirmations');
    }
};
