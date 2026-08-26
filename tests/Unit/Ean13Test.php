<?php

namespace Tests\Unit;

use App\Support\Ean13;
use PHPUnit\Framework\TestCase;

/** The bars of an EAN-13 barcode. */
class Ean13Test extends TestCase
{
    public function test_a_thirteen_digit_code_makes_ninety_five_modules(): void
    {
        $modules = Ean13::modules('4006381333931');

        $this->assertSame(95, strlen((string) $modules));
        // Start guard, centre guard, end guard: a scanner looks for these
        // three before it reads anything.
        $this->assertSame('101', substr((string) $modules, 0, 3));
        $this->assertSame('01010', substr((string) $modules, 45, 5));
        $this->assertSame('101', substr((string) $modules, -3));
    }

    public function test_a_twelve_digit_code_is_an_ean_with_a_leading_zero(): void
    {
        $this->assertSame(Ean13::modules('0012345678905'), Ean13::modules('012345678905'));
        $this->assertSame('0012345678905', Ean13::normalise('012345678905'));
    }

    public function test_the_first_digit_is_carried_by_parity_not_by_bars(): void
    {
        // Same twelve digits, different leading digit: the drawing changes
        // even though no extra bar is added.
        $one = Ean13::modules('1006381333931');
        $four = Ean13::modules('4006381333931');

        $this->assertNotSame($one, $four);
        $this->assertSame(strlen((string) $one), strlen((string) $four));
    }

    public function test_a_code_of_another_length_gets_no_bars(): void
    {
        // EAN-8 and GTIN-14 are different symbologies: the label prints the
        // digits alone rather than drawing something a scanner would misread.
        $this->assertNull(Ean13::modules('12345670'));
        $this->assertNull(Ean13::modules('12345678901234'));
        $this->assertNull(Ean13::modules(null));
    }

    public function test_the_check_digit_is_verified(): void
    {
        $this->assertTrue(Ean13::checkDigitIsValid('4006381333931'));
        $this->assertTrue(Ean13::checkDigitIsValid('5901234123457'));
        $this->assertFalse(Ean13::checkDigitIsValid('4006381333930'));
        $this->assertFalse(Ean13::checkDigitIsValid('123'));
    }
}
