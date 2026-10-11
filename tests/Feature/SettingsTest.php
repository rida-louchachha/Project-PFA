<?php

namespace Tests\Feature;

use App\Support\Currency;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    public function test_public_settings_expose_the_currency_display(): void
    {
        $this->getJson('/api/settings')
            ->assertOk()
            ->assertJsonPath('data.currency.code', 'MAD')
            ->assertJsonPath('data.currency.symbol', 'DH');
    }

    public function test_the_display_symbol_is_configurable(): void
    {
        $this->assertSame('DH', Currency::label('MAD'));
        $this->assertSame('DH', Currency::label(null));
        $this->assertSame('EUR', Currency::label('EUR'));

        config(['travelplan.currency.symbol' => 'MAD']);
        $this->assertSame('MAD', Currency::label('MAD'));
    }
}
