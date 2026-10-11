<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id('id_message');
            $table->foreignId('id_conversation')->constrained('ai_conversations', 'id_conversation')->cascadeOnDelete();

            // customer = client, assistant = TravelPlan AI, agent = human agency advisor
            $table->enum('role', ['customer', 'assistant', 'agent']);
            $table->text('content');

            // chat | welcome | booking_confirmation | intention_request | intention_followup | arrival_reminder
            $table->string('kind', 40)->default('chat');

            // Arrival intention detected on a customer message (audit trail)
            $table->string('intent', 40)->nullable();

            // Human agent who wrote the message (role = agent)
            $table->foreignId('id_auteur')->nullable()->constrained('utilisateurs', 'id_utilisateur')->nullOnDelete();

            // Technical audit data only (provider, model, tools used, latency, fallback). No personal data.
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['id_conversation', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
