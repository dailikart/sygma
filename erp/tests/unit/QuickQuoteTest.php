<?php

use App\Libraries\QuickQuote;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * One-click quotation maths, checked against a real 4.4 kW proposal.
 *
 * @internal
 */
final class QuickQuoteTest extends CIUnitTestCase
{
    private array $cfg;

    protected function setUp(): void
    {
        parent::setUp();
        helper('app');
        $this->cfg = QuickQuote::defaults();
        $this->cfg['sun_hours']  = 5;
        $this->cfg['efficiency'] = 80; // 4 units / kW / day
    }

    private function build(array $in): array
    {
        return QuickQuote::build($in + ['mode' => 'kw', 'system' => 'ongrid_dcr', 'tech' => 'bifacial', 'watt' => 550, 'panel_brand' => 'Adani', 'phase' => 1], $this->cfg);
    }

    public function testSizesPanelsInverterAndSubsidyLikeTheSample(): void
    {
        $r = $this->build(['kw' => 4.4]);

        $this->assertTrue($r['ok']);
        $this->assertSame(8, $r['size']['panels']);
        $this->assertSame(4.4, $r['size']['kw']);
        $this->assertEquals(4, $r['size']['inverter_kw']); // 4.4 kW of panels → 4 kW inverter
        $this->assertEquals(78000, $r['subsidy']['central']);
        $this->assertEquals(22000, $r['subsidy']['state']);
        $this->assertSame('4.4kW On-Grid DCR', $r['title']);
        $this->assertSame(8.9, $r['gst']['blended']);
    }

    public function testPanelsRoundUpSoTheSystemIsNeverSmaller(): void
    {
        $this->assertSame(6, $this->build(['kw' => 3])['size']['panels']); // 5.45 → 6 panels = 3.3 kW
        $this->assertSame(3.3, $this->build(['kw' => 3])['size']['kw']);
    }

    public function testSizingFromTheEbBill(): void
    {
        // 960 units in 60 days at 4 units/kW/day = 4 kW → 8 × 550 W
        $r = $this->build(['mode' => 'bill', 'units' => 960]);
        $this->assertSame(8, $r['size']['panels']);
        $this->assertFalse($this->build(['mode' => 'bill'])['ok']);
    }

    public function testBiMonthlySavingUsesTheSlabTariff(): void
    {
        // 4.4 kW × 4 × 365 / 6 = 1071 units → TNEB slabs ≈ ₹7,452 (the sample shows ₹7,452)
        $r = $this->build(['kw' => 4.4]);
        $this->assertEqualsWithDelta(7452, $r['savings']['bimonthly'], 2);
        $this->assertSame(0.0, QuickQuote::slabBill(100, $this->cfg['tariff']));
        $this->assertEqualsWithDelta(235.0, QuickQuote::slabBill(200, $this->cfg['tariff']), 0.01);
        $this->assertEqualsWithDelta(300.0, QuickQuote::unitsFromBill(705, $this->cfg['tariff']), 0.1);
    }

    public function testGstSplitAddsBackToTheTotal(): void
    {
        $r = $this->build(['kw' => 4.4]);
        $g = $r['gst'];
        $total = round($g['goods_value'] * 1.05 + $g['service_value'] * 1.18, 2);
        $this->assertEqualsWithDelta($r['price']['total'], $total, 0.05);
        $this->assertEqualsWithDelta(0.3, $g['service_value'] / ($g['goods_value'] + $g['service_value']), 0.001);
    }

    public function testPriceIsCostPlusMarginOrPackage(): void
    {
        $r = $this->build(['kw' => 4.4]);
        $this->assertEquals(round($r['cost']['total'] * 1.10), $r['price']['list']);

        $this->cfg['use_packages'] = true;
        $this->cfg['packages']     = [['system' => 'ongrid_dcr', 'kw' => 4, 'price' => 230000, 'name' => '']];
        $this->assertEquals(230000, $this->build(['kw' => 4.4])['price']['total']); // 4 ≤ 4.4 < 4.5
        $this->assertNotEquals(230000, $this->build(['kw' => 5])['price']['total']);
        $this->assertEquals(225000, $this->build(['kw' => 4.4, 'adjust' => -5000])['price']['total']);
    }

    public function testNdcrGetsNoSubsidyAndMicroHasNoDcdb(): void
    {
        $this->assertEquals(0, $this->build(['kw' => 3, 'system' => 'ongrid_ndcr', 'panel_brand' => 'Waaree'])['subsidy']['total']);
        $micro = $this->build(['kw' => 3, 'system' => 'micro']);
        $this->assertNotContains('DCDB', array_column($micro['bom'], 0));
        $this->assertEquals(6 * 8500, $micro['cost']['lines']['inverter']);
    }

    public function testProjectionMatchesTheSampleNetGain(): void
    {
        // Sample: ₹44,713 a year, net cost ₹1,35,866, 6% tariff rise, 30 years → ₹33,99,040
        $p = QuickQuote::projection(44713, 135866, 6, 0, 30);
        $this->assertEqualsWithDelta(3399040, $p['net_gain'], 50);
        $this->assertEqualsWithDelta(2.9, $p['payback'], 0.1);
        $this->assertCount(31, $p['rows']);
    }

    public function testSanitizeKeepsDeletedListsEmptyAndFixesBadValues(): void
    {
        $c = QuickQuote::sanitize(['_form' => 1, 'margin' => '-5', 'efficiency' => 150, 'default_system' => 'nope']);
        $this->assertSame([], $c['state_subsidy']);
        $this->assertSame([], $c['panels']);
        $this->assertSame(0.0, $c['margin']);
        $this->assertSame(100.0, $c['efficiency']);
        $this->assertSame('ongrid_dcr', $c['default_system']);
        $this->assertNotEmpty(QuickQuote::sanitize([])['panels']); // a saved config missing a key falls back to defaults
    }

    public function testChartIsValidSvg(): void
    {
        $svg = QuickQuote::chartSvg(QuickQuote::projection(44713, 135866, 6, 0, 30)['rows']);
        $this->assertNotFalse(simplexml_load_string($svg));
        $this->assertSame(31, substr_count($svg, '<rect'));
    }
}
