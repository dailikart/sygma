<?php

use App\Libraries\Agreement;
use App\Libraries\QuickQuote;
use App\Libraries\Tax;
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
        $this->cfg['line_gst']   = 'split';
    }

    private const ITEMS = [
        ['id' => 11, 'name' => 'Adani 550W Bifacial DCR Module', 'unit' => 'Nos', 'hsn' => '854143', 'tax_percent' => 5, 'stock' => 40],
        ['id' => 12, 'name' => 'Waaree 550 Wp Mono PERC panel', 'unit' => 'Nos', 'hsn' => '854143', 'tax_percent' => 5, 'stock' => 3],
        ['id' => 21, 'name' => 'Growatt 4kW On-Grid Inverter 1Ph', 'unit' => 'Nos', 'hsn' => '850440', 'tax_percent' => 5, 'stock' => 2],
        ['id' => 22, 'name' => 'Growatt 5 kW Hybrid Inverter', 'unit' => 'Nos', 'hsn' => '850440', 'tax_percent' => 5, 'stock' => 1],
        ['id' => 31, 'name' => 'GI Mounting Structure', 'unit' => 'Set', 'hsn' => '7308', 'tax_percent' => 5, 'stock' => 9],
        ['id' => 32, 'name' => 'DCDB 1 in 1 out', 'unit' => 'Nos', 'hsn' => '8537', 'tax_percent' => 5, 'stock' => 5],
        ['id' => 33, 'name' => 'ACDB Single phase', 'unit' => 'Nos', 'hsn' => '8537', 'tax_percent' => 5, 'stock' => 5],
        ['id' => 34, 'name' => 'Solar DC cable 4 sq.mm', 'unit' => 'Mtr', 'hsn' => '8544', 'tax_percent' => 5, 'stock' => 500],
        ['id' => 35, 'name' => 'Earthing Chemical Electrode Kit', 'unit' => 'Nos', 'hsn' => '8546', 'tax_percent' => 5, 'stock' => 12],
        ['id' => 36, 'name' => 'Earth cable 6 sq.mm', 'unit' => 'Mtr', 'hsn' => '8544', 'tax_percent' => 5, 'stock' => 300],
        ['id' => 37, 'name' => 'Lightning Arrester ESE', 'unit' => 'Nos', 'hsn' => '8535', 'tax_percent' => 5, 'stock' => 4],
    ];

    private function itemsBuild(array $in = []): array
    {
        $this->cfg['line_gst'] = 'items';

        return QuickQuote::build($in + ['mode' => 'kw', 'kw' => 4.4, 'system' => 'ongrid_dcr', 'tech' => 'bifacial', 'watt' => 550, 'panel_brand' => 'Adani', 'phase' => 1, 'inverter_brand' => 'Growatt'],
            $this->cfg, 'Sunrise Solar', self::ITEMS);
    }

    public function testItemsAreMatchedByNameAndLinesAddUpToThePrice(): void
    {
        $r   = $this->itemsBuild();
        $bom = array_column($r['bom'], null, 'key');
        $this->assertSame(11, $bom['panels']['item_id']);   // brand + 550W + bifacial
        $this->assertSame(21, $bom['inverter']['item_id']); // brand + 4kW, not the hybrid
        $this->assertSame(31, $bom['structure']['item_id']);
        $this->assertSame(32, $bom['dcdb']['item_id']);
        $this->assertSame(33, $bom['acdb']['item_id']);
        $this->assertSame(34, $bom['dc_cable']['item_id']);
        $this->assertSame(35, $bom['earth_pit']['item_id']);
        $this->assertSame(36, $bom['earth_cable']['item_id']);
        $this->assertSame(37, $bom['la']['item_id']);
        $this->assertSame(0, $bom['ac_cable']['item_id']);    // no AC cable item: typed line
        $this->assertTrue($bom['panels']['item_auto']);

        [$lines, , , $total] = Tax::lines($r['lines']);
        $this->assertEqualsWithDelta($r['price']['total'], $total, 0.02);
        $byItem = array_column(array_filter($lines, static fn ($l) => $l['item_id']), null, 'item_id');
        $this->assertEquals(8, $byItem[11]['qty']);  // stock qty kept on linked items
        $this->assertEquals(3, $byItem[35]['qty']);
        $this->assertEquals('854143', $byItem[11]['hsn']);
        $install = array_values(array_filter($lines, static fn ($l) => str_starts_with($l['description'], 'Installation')))[0];
        $this->assertEquals(18, $install['tax_percent']); // service rate, lump sum
        $this->assertEquals(1, $install['qty']);
    }

    public function testExplicitItemLinkWinsOverNameMatch(): void
    {
        $this->cfg['panels'][0]['item_id'] = 12; // Adani bifacial DCR row linked to the Waaree item
        $this->cfg['item_map']['acdb']      = 32;
        $bom = array_column($this->itemsBuild()['bom'], null, 'key');
        $this->assertSame(12, $bom['panels']['item_id']);
        $this->assertFalse($bom['panels']['item_auto']);
        $this->assertSame(32, $bom['acdb']['item_id']);
        $this->assertEquals(3, $bom['panels']['stock']);
    }

    public function testHybridPicksTheHybridInverter(): void
    {
        $bom = array_column($this->itemsBuild(['system' => 'hybrid', 'kw' => 5.5])['bom'], null, 'key');
        $this->assertSame(22, $bom['inverter']['item_id']);
        $this->assertArrayHasKey('battery', $bom);
    }

    public function testAgreementFillsPlaceholdersAndEscapes(): void
    {
        $html = Agreement::render("# AGREEMENT\n- Clause for **{customer_name}** of {customer_address}\n- Second {missing_value} {father_name}\n> note", [
            'customer_name' => 'Ramesh <b>', 'customer_address' => 'Arakkonam', 'father_name' => '',
        ], [], 100000, ['customer' => 'Ramesh', 'company' => 'Sunrise', 'signature' => '']);
        $this->assertStringContainsString('<b>Ramesh &lt;b&gt;</b>', $html);
        $this->assertStringContainsString('2.</td>', $html);       // numbered clauses
        $this->assertStringContainsString('{missing_value}', $html); // unknown placeholders are left as typed
        $this->assertStringContainsString('________________', $html); // empty values print a blank
        $sched = Agreement::render('{payment_schedule}', [], [['name' => 'Advance', 'pct' => 10, 'text' => 'now'], ['name' => 'Balance', 'pct' => 90, 'text' => 'later']], 235866, []);
        $this->assertStringContainsString('₹23,587', $sched);
        $this->assertStringContainsString('100%', $sched);
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
        $this->assertEquals(8.9, $r['gst']['blended']);
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
        [$lines, $sub, , $total] = Tax::lines($r['lines']);
        $this->assertCount(2, $lines);
        $this->assertEqualsWithDelta($r['price']['total'], $total, 0.05);
        $this->assertEqualsWithDelta(0.3, $lines[1]['amount'] / $sub, 0.001);
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
