<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery log + idempotency key for automated booking communications.
 * The unique (reservation, type, channel) index guarantees a message is sent once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_notifications', function (Blueprint $table) {
            $table->id('id_notification');
            $table->foreignId('id_reservation')->constrained('reservations', 'id_reservation')->cascadeOnDelete();
            $table->enum('type', ['booking_confirmation', 'intention_request', 'intention_followup', 'arrival_reminder']);
            $table->string('channel', 20)->default('mail');
            $table->enum('statut', ['processing', 'sent', 'failed'])->default('processing');
            $table->unsignedTinyInteger('attempts')->default(0);
            // ai = personalised by the LLM, template = deterministic fallback
            $table->string('content_source', 20)->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['id_reservation', 'type', 'channel']);
            $table->index(['statut', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_notifications');
    }
};
