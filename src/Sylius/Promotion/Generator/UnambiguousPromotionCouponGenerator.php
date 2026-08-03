<?php

namespace AppBundle\Sylius\Promotion\Generator;

use Doctrine\Persistence\ObjectManager;
use Sylius\Component\Promotion\Generator\GenerationPolicyInterface;
use Sylius\Component\Promotion\Generator\PromotionCouponGeneratorInterface;
use Sylius\Component\Promotion\Generator\ReadablePromotionCouponGeneratorInstructionInterface;
use Sylius\Component\Promotion\Model\PromotionCouponInterface;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

/**
 * Drop-in replacement for Sylius' PromotionCouponGenerator.
 *
 * The stock generator draws codes from hex (bin2hex + strtoupper => 0-9A-F,
 * only 16 symbols), which forces long codes and produces glyph-ambiguous
 * output. This one draws uniformly from a configurable, unambiguous alphabet
 * (see CouponCodeAlphabet, 31 symbols by default), so shorter codes hold far
 * more combinations: 31**5 ~= 28.6M, comfortably above e.g. 37k coupons.
 *
 * @see \Sylius\Component\Promotion\Generator\PromotionCouponGenerator
 */
final class UnambiguousPromotionCouponGenerator implements PromotionCouponGeneratorInterface
{
    /** @param FactoryInterface<PromotionCouponInterface> $couponFactory */
    public function __construct(
        private FactoryInterface $couponFactory,
        private PromotionCouponRepositoryInterface $couponRepository,
        private ObjectManager $objectManager,
        private GenerationPolicyInterface $generationPolicy,
        private string $alphabet = CouponCodeAlphabet::ALPHABET,
    ) {
        Assert::notEmpty($this->alphabet, 'Coupon code alphabet cannot be empty.');
    }

    public function generate(
        PromotionInterface $promotion,
        ReadablePromotionCouponGeneratorInstructionInterface $instruction,
    ): array {
        $generatedCoupons = [];

        $this->assertGenerationIsPossible($instruction);

        for ($i = 0, $amount = $instruction->getAmount(); $i < $amount; ++$i) {
            $code = $this->generateUniqueCode(
                $instruction->getCodeLength(),
                $generatedCoupons,
                $instruction->getPrefix(),
                $instruction->getSuffix(),
            );

            /** @var PromotionCouponInterface $coupon */
            $coupon = $this->couponFactory->createNew();
            $coupon->setPromotion($promotion);
            $coupon->setCode($code);
            $coupon->setUsageLimit($instruction->getUsageLimit());
            $coupon->setExpiresAt($instruction->getExpiresAt());

            $generatedCoupons[$code] = $coupon;

            $this->objectManager->persist($coupon);
        }

        $this->objectManager->flush();

        return $generatedCoupons;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function generateUniqueCode(
        int $codeLength,
        array $generatedCoupons,
        ?string $prefix,
        ?string $suffix,
    ): string {
        Assert::nullOrRange($codeLength, 1, 40, 'Invalid %d code length should be between %d and %d');

        do {
            $code = $prefix . $this->randomCode($codeLength) . $suffix;
        } while ($this->isUsedCode($code, $generatedCoupons));

        return $code;
    }

    /**
     * Uniform, bias-free random draw from the alphabet (random_int is
     * cryptographically secure and rejection-samples internally).
     */
    private function randomCode(int $codeLength): string
    {
        $max = strlen($this->alphabet) - 1;
        $code = '';
        for ($i = 0; $i < $codeLength; ++$i) {
            $code .= $this->alphabet[random_int(0, $max)];
        }

        return $code;
    }

    private function isUsedCode(string $code, array $generatedCoupons): bool
    {
        if (isset($generatedCoupons[$code])) {
            return true;
        }

        return null !== $this->couponRepository->findOneBy(['code' => $code]);
    }

    private function assertGenerationIsPossible(ReadablePromotionCouponGeneratorInstructionInterface $instruction): void
    {
        if (!$this->generationPolicy->isGenerationPossible($instruction)) {
            throw new \Sylius\Component\Promotion\Exception\FailedGenerationException($instruction);
        }
    }
}
