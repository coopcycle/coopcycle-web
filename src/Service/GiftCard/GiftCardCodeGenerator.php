<?php

namespace AppBundle\Service\GiftCard;

use AppBundle\Entity\GiftCard\GiftCardRepository;
use AppBundle\Sylius\Promotion\Generator\CouponCodeAlphabet;

/**
 * Mints gift card codes from the same unambiguous alphabet as promotion
 * coupons and referral codes, so a code stays readable when it is printed on
 * a card or dictated down the phone.
 *
 * Longer than a coupon code, though. A coupon is a discount somebody has to
 * be told about; a gift card code *is* the money, and anyone who guesses one
 * can spend it. At 12 characters from a 31-symbol alphabet that is roughly
 * 7.8e17 possibilities -- enough that guessing is not a strategy, even
 * against an instance with a lot of cards outstanding.
 */
class GiftCardCodeGenerator
{
    private const CODE_LENGTH = 12;

    /**
     * Codes are grouped for legibility when shown, never when stored: the
     * repository strips separators before comparing, so a customer can type
     * it either way.
     */
    private const GROUP_SIZE = 4;

    public function __construct(private readonly GiftCardRepository $giftCardRepository)
    {
    }

    public function generate(): string
    {
        do {
            $code = $this->randomCode();
        } while (null !== $this->giftCardRepository->findOneByCode($code));

        return $code;
    }

    public static function format(string $code): string
    {
        return implode('-', str_split($code, self::GROUP_SIZE));
    }

    private function randomCode(): string
    {
        $alphabet = CouponCodeAlphabet::ALPHABET;
        $base = CouponCodeAlphabet::base();

        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            // random_int, not rand/mt_rand: this value is worth money, so it
            // has to come from a cryptographically secure source.
            $code .= $alphabet[random_int(0, $base - 1)];
        }

        return $code;
    }
}
