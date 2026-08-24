<?php

namespace AppBundle\Sylius\Promotion\Generator;

use Sylius\Component\Promotion\Generator\GenerationPolicyInterface;
use Sylius\Component\Promotion\Generator\ReadablePromotionCouponGeneratorInstructionInterface;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;
use Webmozart\Assert\Assert;

/**
 * Capacity guard for UnambiguousPromotionCouponGenerator.
 *
 * Mirrors Sylius' PercentageGenerationPolicy but parameterises the alphabet
 * size instead of hardcoding 16 (hex). Generation is only allowed while the
 * requested amount stays under `base ** codeLength * ratio`; the default
 * ratio of 0.5 keeps the code space at most half full so the generator's
 * uniqueness retry loop stays cheap and collisions negligible.
 *
 * @see \Sylius\Component\Promotion\Generator\PercentageGenerationPolicy
 */
final class AlphabetGenerationPolicy implements GenerationPolicyInterface
{
    private int $base;

    public function __construct(
        private PromotionCouponRepositoryInterface $couponRepository,
        string $alphabet = CouponCodeAlphabet::ALPHABET,
        private float $ratio = 0.5,
    ) {
        Assert::notEmpty($alphabet, 'Coupon code alphabet cannot be empty.');
        $this->base = strlen($alphabet);
    }

    public function isGenerationPossible(ReadablePromotionCouponGeneratorInstructionInterface $instruction): bool
    {
        return $this->calculatePossibleGenerationAmount($instruction) >= $instruction->getAmount();
    }

    public function getPossibleGenerationAmount(ReadablePromotionCouponGeneratorInstructionInterface $instruction): int
    {
        return $this->calculatePossibleGenerationAmount($instruction);
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function calculatePossibleGenerationAmount(ReadablePromotionCouponGeneratorInstructionInterface $instruction): int
    {
        $expectedAmount = $instruction->getAmount();
        $expectedCodeLength = $instruction->getCodeLength();

        Assert::allNotNull(
            [$expectedAmount, $expectedCodeLength],
            'Code length or amount cannot be null.',
        );

        $generatedAmount = $this->couponRepository->countByCodeLength(
            $expectedCodeLength,
            $instruction->getPrefix(),
            $instruction->getSuffix(),
        );

        $codeCombination = $this->base ** $expectedCodeLength * $this->ratio;
        if ($codeCombination >= \PHP_INT_MAX) {
            return \PHP_INT_MAX - $generatedAmount;
        }

        return (int) $codeCombination - $generatedAmount;
    }
}
