<?php

namespace AppBundle\Service\Referral;

use AppBundle\Entity\Sylius\Customer;
use AppBundle\Sylius\Promotion\Generator\CouponCodeAlphabet;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * Mints a customer's own shareable referral code, drawn from the same
 * unambiguous alphabet as promotion coupon codes for visual consistency
 * (see UnambiguousPromotionCouponGenerator).
 */
class ReferralCodeGenerator
{
    private const CODE_LENGTH = 6;

    public function __construct(
        private readonly RepositoryInterface $customerRepository)
    {
    }

    public function generateFor(Customer $customer): string
    {
        do {
            $code = $this->randomCode();
        } while (null !== $this->customerRepository->findOneBy(['referralCode' => $code]));

        $customer->setReferralCode($code);

        return $code;
    }

    private function randomCode(): string
    {
        $alphabet = CouponCodeAlphabet::ALPHABET;
        $base = CouponCodeAlphabet::base();

        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $alphabet[random_int(0, $base - 1)];
        }

        return $code;
    }
}
