<?php

namespace Tests\Unit;

use App\Services\Ai\Providers\IntentHeuristics;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IntentHeuristicsTest extends TestCase
{
    public static function phrases(): array
    {
        return [
            // Examples from the specification
            ['Oui, je confirme.', 'confirmed'],
            ["Oui, c'est toujours bon.", 'confirmed'],
            ['Bien sûr, nous arrivons demain.', 'confirmed'],
            ['Je ne suis plus sûr.', 'uncertain'],
            ['Non, je souhaite annuler.', 'declined'],
            ['Je veux modifier mes dates.', 'modification_requested'],
            ["Je veux parler à l'agence.", 'human_assistance_required'],
            // English
            ["Yes, we're still coming!", 'confirmed'],
            ['Maybe, not sure yet', 'uncertain'],
            ['I need to reschedule my booking', 'modification_requested'],
            // Moroccan Darija (Latin script)
            ['iyeh ghadi nji', 'confirmed'],
            ['wakha', 'confirmed'],
            ['mabghitch nji, bghit nlghi', 'declined'],
            // Questions are not decisions
            ['Est-ce que je peux annuler ?', 'none'],
            ['Est-ce que ma réservation est confirmée ?', 'none'],
            ['Où se trouve mon hôtel ?', 'none'],
        ];
    }

    #[DataProvider('phrases')]
    public function test_it_classifies_customer_phrases(string $message, string $expected): void
    {
        $this->assertSame($expected, IntentHeuristics::classify($message, awaitingAnswer: true)['intention']);
    }

    public function test_bare_yes_only_counts_when_an_arrival_question_is_pending(): void
    {
        $this->assertGreaterThanOrEqual(0.6, IntentHeuristics::classify('Oui', awaitingAnswer: true)['confidence']);
        $this->assertLessThan(0.6, IntentHeuristics::classify('Oui', awaitingAnswer: false)['confidence']);
    }

    public function test_it_detects_language(): void
    {
        $this->assertSame('darija', IntentHeuristics::classify('iyeh ghadi nji')['language']);
        $this->assertSame('en', IntentHeuristics::classify('yes we are coming')['language']);
        $this->assertSame('fr', IntentHeuristics::classify('Oui, je confirme')['language']);
    }
}
