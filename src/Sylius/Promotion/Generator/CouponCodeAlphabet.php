<?php

namespace AppBundle\Sylius\Promotion\Generator;

/**
 * Human-readable, unambiguous alphabet shared by the coupon code generator
 * and its generation policy. Both MUST use the same alphabet, otherwise the
 * policy's capacity math (base ** length) will not match what the generator
 * can actually produce.
 *
 * Ambiguous glyphs are deliberately excluded so codes stay readable when
 * printed or dictated: 0/O, 1/I/L. This yields a 31-character alphabet.
 */
final class CouponCodeAlphabet
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private function __construct()
    {
    }

    public static function base(): int
    {
        return strlen(self::ALPHABET);
    }
}
