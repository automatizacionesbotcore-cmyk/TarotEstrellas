<?php

namespace Tests\Unit;

use App\Support\GrabacionPricing;
use PHPUnit\Framework\TestCase;

class GrabacionPricingTest extends TestCase
{
    public function test_clp_tiers_match_pricing_table(): void
    {
        $this->assertSame(250000, GrabacionPricing::extraCentavos(30, 'CLP'));
        $this->assertSame(399000, GrabacionPricing::extraCentavos(60, 'CLP'));
        $this->assertSame(599000, GrabacionPricing::extraCentavos(90, 'CLP'));
    }

    public function test_clp_unknown_duration_prorrates_against_60min_tier(): void
    {
        $expected = (int) round((45 / 60) * 399000);
        $this->assertSame($expected, GrabacionPricing::extraCentavos(45, 'CLP'));
    }

    public function test_non_clp_currency_uses_usd_fallback(): void
    {
        $this->assertSame(499, GrabacionPricing::extraCentavos(60, 'USD'));
        $this->assertSame((int) round((30 / 60) * 499), GrabacionPricing::extraCentavos(30, 'USD'));
    }

    public function test_format_currency_clp_no_decimals(): void
    {
        $this->assertStringContainsString('CLP', GrabacionPricing::formatear(250000, 'CLP'));
        $this->assertStringContainsString('2.500', GrabacionPricing::formatear(250000, 'CLP'));
    }
}
