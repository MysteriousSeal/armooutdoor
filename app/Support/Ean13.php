<?php

namespace App\Support;

/**
 * An EAN-13 barcode, as the bars themselves.
 *
 * No library: the encoding is a hundred lines of tables, and pulling in a
 * dependency to draw ninety-five black rectangles would cost more than it
 * saves. The renderer turns the string of bits this returns into bars.
 */
class Ean13
{
    /** Modules per digit, on the left of the barcode, odd parity. */
    private const LEFT_ODD = [
        '0001101', '0011001', '0010011', '0111101', '0100011',
        '0110001', '0101111', '0111011', '0110111', '0001011',
    ];

    /** The same digits, even parity. Which of the two is used encodes the first digit. */
    private const LEFT_EVEN = [
        '0100111', '0110011', '0011011', '0100001', '0011101',
        '0111001', '0000101', '0010001', '0001001', '0010111',
    ];

    /** Modules per digit on the right, always the same, always ending in a bar. */
    private const RIGHT = [
        '1110010', '1100110', '1101100', '1000010', '1011100',
        '1001110', '1010000', '1000100', '1001000', '1110100',
    ];

    /**
     * How the first digit is carried: it is never drawn, only implied by the
     * parity chosen for each of the six digits on the left.
     */
    private const PARITY = [
        'OOOOOO', 'OOEOEE', 'OOEEOE', 'OOEEEO', 'OEOOEE',
        'OEEOOE', 'OEEEOO', 'OEOEOE', 'OEOEEO', 'OEEOEO',
    ];

    /**
     * The 95 modules of a barcode, as '0' and '1', or null when the code
     * cannot be drawn as an EAN-13.
     *
     * A 12-digit UPC-A is an EAN-13 with a leading zero. An 8 or 14 digit
     * code is a different symbology, and gets no bars — the digits are
     * printed on their own instead.
     */
    public static function modules(?string $gtin): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $gtin);

        if (strlen($digits) === 12) {
            $digits = '0'.$digits;
        }

        if (strlen($digits) !== 13) {
            return null;
        }

        $parity = self::PARITY[(int) $digits[0]];

        // Start guard, six left digits, centre guard, six right digits, end guard.
        $modules = '101';

        for ($i = 1; $i <= 6; $i++) {
            $digit = (int) $digits[$i];
            $modules .= $parity[$i - 1] === 'O'
                ? self::LEFT_ODD[$digit]
                : self::LEFT_EVEN[$digit];
        }

        $modules .= '01010';

        for ($i = 7; $i <= 12; $i++) {
            $modules .= self::RIGHT[(int) $digits[$i]];
        }

        return $modules.'101';
    }

    /** The code as it is printed under the bars, 13 digits with no spaces. */
    public static function normalise(?string $gtin): string
    {
        $digits = preg_replace('/\D/', '', (string) $gtin);

        return strlen($digits) === 12 ? '0'.$digits : $digits;
    }

    /**
     * Whether the last digit agrees with the twelve before it.
     *
     * A wrong check digit still prints — the label reports what the catalogue
     * holds — but the page can say so.
     */
    public static function checkDigitIsValid(?string $gtin): bool
    {
        $digits = self::normalise($gtin);

        if (strlen($digits) !== 13) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $digits[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10 === (int) $digits[12];
    }
}
