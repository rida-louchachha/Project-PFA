<?php

namespace Tests\Unit;

use App\Services\Ai\Support\GroundingGuard;
use PHPUnit\Framework\TestCase;

class GroundingGuardTest extends TestCase
{
    public function test_numbers_present_in_the_retrieved_data_are_grounded(): void
    {
        $sources = [['montant_total' => '1250.00', 'date_arrivee' => '2026-10-20 14:00', 'nb_personnes' => 2]];
        $reply = 'Vous avez payé 1 250,00 MAD pour 2 personnes, arrivée le 20 octobre 2026 à 14h00.';

        $this->assertSame([], GroundingGuard::ungroundedNumbers($reply, $sources));
    }

    public function test_invented_amounts_are_flagged(): void
    {
        $sources = [['montant_total' => '1250.00']];

        $this->assertSame(['990'], GroundingGuard::ungroundedNumbers('Vous avez payé 990 MAD.', $sources));
    }

    public function test_digit_detection_for_outbound_ai_sentences(): void
    {
        $this->assertTrue(GroundingGuard::containsDigits('Votre séjour du 20 octobre'));
        $this->assertFalse(GroundingGuard::containsDigits('Marrakech vous attend sous le soleil.'));
    }
}
