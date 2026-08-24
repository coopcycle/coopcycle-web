<?php

namespace Tests\AppBundle\Sylius\Promotion\Generator;

use AppBundle\Entity\Sylius\PromotionCoupon;
use AppBundle\Sylius\Promotion\Generator\AlphabetGenerationPolicy;
use AppBundle\Sylius\Promotion\Generator\CouponCodeAlphabet;
use AppBundle\Sylius\Promotion\Generator\UnambiguousPromotionCouponGenerator;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Sylius\Component\Promotion\Exception\FailedGenerationException;
use Sylius\Component\Promotion\Generator\ReadablePromotionCouponGeneratorInstructionInterface;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;

class UnambiguousPromotionCouponGeneratorTest extends TestCase
{
    use ProphecyTrait;

    private $couponFactory;
    private $couponRepository;
    private $objectManager;
    private $generator;

    public function setUp(): void
    {
        $this->couponFactory = $this->prophesize(FactoryInterface::class);
        $this->couponRepository = $this->prophesize(PromotionCouponRepositoryInterface::class);
        $this->objectManager = $this->prophesize(ObjectManager::class);

        // A fresh entity per createNew() call, so each coupon records its own code.
        $this->couponFactory->createNew()->will(function () {
            return new PromotionCoupon();
        });

        // No pre-existing coupons in the database.
        $this->couponRepository->findOneBy(Argument::type('array'))->willReturn(null);
        $this->couponRepository->countByCodeLength(Argument::cetera())->willReturn(0);

        // Real policy, so the capacity gate is genuinely exercised.
        $policy = new AlphabetGenerationPolicy($this->couponRepository->reveal());

        $this->generator = new UnambiguousPromotionCouponGenerator(
            $this->couponFactory->reveal(),
            $this->couponRepository->reveal(),
            $this->objectManager->reveal(),
            $policy,
        );
    }

    private function instruction(
        int $amount,
        int $codeLength,
        ?string $prefix = null,
        ?string $suffix = null,
    ): ReadablePromotionCouponGeneratorInstructionInterface {
        $instruction = $this->prophesize(ReadablePromotionCouponGeneratorInstructionInterface::class);
        $instruction->getAmount()->willReturn($amount);
        $instruction->getCodeLength()->willReturn($codeLength);
        $instruction->getPrefix()->willReturn($prefix);
        $instruction->getSuffix()->willReturn($suffix);
        $instruction->getUsageLimit()->willReturn(1);
        $instruction->getExpiresAt()->willReturn(null);

        return $instruction->reveal();
    }

    public function testGeneratesRequestedAmount()
    {
        $coupons = $this->generator->generate(
            $this->prophesize(PromotionInterface::class)->reveal(),
            $this->instruction(50, 5),
        );

        $this->assertCount(50, $coupons);
    }

    public function testCodesUseOnlyUnambiguousAlphabet()
    {
        $coupons = $this->generator->generate(
            $this->prophesize(PromotionInterface::class)->reveal(),
            $this->instruction(200, 5),
        );

        $pattern = sprintf('/^[%s]{5}$/', preg_quote(CouponCodeAlphabet::ALPHABET, '/'));
        foreach ($coupons as $coupon) {
            $this->assertMatchesRegularExpression($pattern, $coupon->getCode());
        }
    }

    public function testPrefixAndSuffixWrapTheRandomCode()
    {
        $coupons = $this->generator->generate(
            $this->prophesize(PromotionInterface::class)->reveal(),
            $this->instruction(20, 5, 'SUMMER_', '_2026'),
        );

        $pattern = sprintf('/^SUMMER_[%s]{5}_2026$/', preg_quote(CouponCodeAlphabet::ALPHABET, '/'));
        foreach ($coupons as $coupon) {
            $this->assertMatchesRegularExpression($pattern, $coupon->getCode());
        }
    }

    public function testCodesAreUniqueAcrossABatch()
    {
        $amount = 5000;
        $coupons = $this->generator->generate(
            $this->prophesize(PromotionInterface::class)->reveal(),
            $this->instruction($amount, 5),
        );

        // The array is keyed by code, so a collision would shrink the count.
        $this->assertCount($amount, $coupons);
        $this->assertCount($amount, array_unique(array_keys($coupons)));
    }

    public function testThrowsWhenCodeSpaceTooSmallForAmount()
    {
        $this->expectException(FailedGenerationException::class);

        // base=31, length=3 => 31**3 * 0.5 = 14895 usable, below 20000.
        $this->generator->generate(
            $this->prophesize(PromotionInterface::class)->reveal(),
            $this->instruction(20000, 3),
        );
    }
}
