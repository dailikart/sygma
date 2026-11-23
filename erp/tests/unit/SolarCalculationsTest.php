<?php

use App\Libraries\Numbering;
use App\Libraries\TallyXml;
use App\Libraries\Tax;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Money, GST, numbering and Tally XML rules that must never drift.
 *
 * @internal
 */
final class SolarCalculationsTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('app');
    }

    public function testLineTotalsWithMixedGstRates(): void
    {
        [$lines, $sub, $tax, $total] = Tax::lines([
            ['item_id' => 1, 'qty' => 6, 'rate' => 11000, 'tax_percent' => 12],
            ['item_id' => 2, 'qty' => 1, 'rate' => 30000, 'tax_percent' => 12],
            ['item_id' => 3, 'qty' => 1, 'rate' => 8000, 'tax_percent' => 18],
            ['item_id' => 4, 'qty' => 0, 'rate' => 999, 'tax_percent' => 18], // zero qty is dropped
        ]);

        $this->assertCount(3, $lines);
        $this->assertSame(104000.0, $sub);
        $this->assertSame(12960.0, $tax);
        $this->assertSame(116960.0, $total);
        $this->assertSame(7920.0, $lines[0]['tax_amount']);
    }

    public function testFinancialYearRunsAprilToMarch(): void
    {
        $this->assertSame('2026-27', Numbering::financialYear('2026-04-01'));
        $this->assertSame('2025-26', Numbering::financialYear('2026-03-31'));
        $this->assertSame('2026-27', Numbering::financialYear('2026-11-07'));
    }

    public function testTallyDebitsAreNegativeAndDeemedPositive(): void
    {
        $dr = TallyXml::entry('HDFC Bank', 1000, true);
        $cr = TallyXml::entry('Murugan S', 1000, false);

        $this->assertStringContainsString('<ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE><AMOUNT>-1000.00</AMOUNT>', $dr);
        $this->assertStringContainsString('<ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE><AMOUNT>1000.00</AMOUNT>', $cr);
    }

    public function testTallyVoucherXmlIsValidAndEscaped(): void
    {
        $xml = (new TallyXml())->voucher('Receipt', '2026-11-07', 'RCT/2026-27/0001', 'Advance & balance', [
            ['HDFC Bank', 20000, true],
            ['Murugan S <Erode>', 20000, false],
            ['Zero line', 0, false], // skipped
        ], 'Murugan S <Erode>')->render();

        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc);
        $v = $doc->BODY->IMPORTDATA->REQUESTDATA->TALLYMESSAGE->VOUCHER;
        $this->assertSame('20261107', (string) $v->DATE);
        $this->assertSame('Advance & balance', (string) $v->NARRATION);
        $this->assertCount(2, $v->{'ALLLEDGERENTRIES.LIST'});
    }

    public function testIndianMoneyFormatAndWords(): void
    {
        $this->assertSame('1,16,960.00', money(116960, false));
        $this->assertSame('12,34,56,789.50', money(123456789.5, false));
        $this->assertSame('Rupees One Lakh Sixteen Thousand Nine Hundred Sixty Only', amount_in_words(116960));
        $this->assertSame('Rupees Twelve Crore Thirty Four Lakh Fifty Six Thousand Seven Hundred Eighty Nine and Fifty Paise Only', amount_in_words(123456789.50));
    }
}
