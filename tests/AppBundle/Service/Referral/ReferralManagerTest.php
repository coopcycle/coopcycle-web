<?php

namespace Tests\AppBundle\Service\Referral;

use AppBundle\Entity\Referral\Referral;
use AppBundle\Entity\Referral\ReferralRepository;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\User;
use AppBundle\Service\Referral\ReferralCodeGenerator;
use AppBundle\Service\Referral\ReferralManager;
use AppBundle\Service\Referral\ReferralRewardCouponFactory;
use AppBundle\Sylius\Promotion\PromotionCouponInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

class ReferralManagerTest extends TestCase
{
    use ProphecyTrait;

    private $customerRepository;
    private $referralRepository;
    private $referralCodeGenerator;
    private $referralRewardCouponFactory;
    private $entityManager;
    private $logger;
    private $manager;

    protected function setUp(): void
    {
        $this->customerRepository = $this->prophesize(RepositoryInterface::class);
        $this->referralRepository = $this->prophesize(ReferralRepository::class);
        $this->referralCodeGenerator = $this->prophesize(ReferralCodeGenerator::class);
        $this->referralRewardCouponFactory = $this->prophesize(ReferralRewardCouponFactory::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->logger = $this->prophesize(LoggerInterface::class);

        $this->manager = new ReferralManager(
            $this->customerRepository->reveal(),
            $this->referralRepository->reveal(),
            $this->referralCodeGenerator->reveal(),
            $this->referralRewardCouponFactory->reveal(),
            $this->entityManager->reveal(),
            $this->logger->reveal()
        );
    }

    private function buildUserWithCustomer(): array
    {
        $customer = new Customer();
        $user = new User();
        $user->setCustomer($customer);

        return [$user, $customer];
    }

    public function testGeneratesOwnCodeAndDoesNothingElseWhenNoCodeGiven(): void
    {
        [$user, $customer] = $this->buildUserWithCustomer();

        $this->referralCodeGenerator->generateFor($customer)->shouldBeCalledOnce();
        $this->referralRepository->findPendingByReferredCustomer($customer)->shouldNotBeCalled();
        $this->entityManager->persist(Argument::type(Referral::class))->shouldNotBeCalled();
        $this->entityManager->flush()->shouldBeCalledOnce();

        $this->manager->registerPendingReferral($user, null);
    }

    public function testIgnoresUnknownReferralCode(): void
    {
        [$user, $customer] = $this->buildUserWithCustomer();

        $this->referralCodeGenerator->generateFor($customer)->shouldBeCalledOnce();
        $this->customerRepository->findOneBy(['referralCode' => 'UNKNOWN'])->willReturn(null);

        $this->entityManager->persist(Argument::type(Referral::class))->shouldNotBeCalled();
        $this->entityManager->flush()->shouldBeCalledOnce();

        $this->manager->registerPendingReferral($user, 'unknown');
    }

    public function testIgnoresSelfReferral(): void
    {
        [$user, $customer] = $this->buildUserWithCustomer();

        $this->referralCodeGenerator->generateFor($customer)->shouldBeCalledOnce();
        $this->customerRepository->findOneBy(['referralCode' => 'ABC123'])->willReturn($customer);

        $this->entityManager->persist(Argument::type(Referral::class))->shouldNotBeCalled();
        $this->entityManager->flush()->shouldBeCalledOnce();

        $this->manager->registerPendingReferral($user, 'abc123');
    }

    public function testCreatesPendingReferralAndWelcomeCouponOnValidCode(): void
    {
        [$user, $referred] = $this->buildUserWithCustomer();
        $referrer = new Customer();

        $this->referralCodeGenerator->generateFor($referred)->shouldBeCalledOnce();
        $this->customerRepository->findOneBy(['referralCode' => 'ABC123'])->willReturn($referrer);
        $this->referralRepository->findPendingByReferredCustomer($referred)->willReturn(null);

        $coupon = $this->prophesize(PromotionCouponInterface::class)->reveal();
        $this->referralRewardCouponFactory->createReferredWelcomeCoupon($referred)->willReturn($coupon);

        $persisted = null;
        $this->entityManager->persist(Argument::type(Referral::class))
            ->will(function ($args) use (&$persisted) {
                $persisted = $args[0];
            })
            ->shouldBeCalledOnce();
        $this->entityManager->flush()->shouldBeCalledOnce();

        $this->manager->registerPendingReferral($user, 'abc123');

        $this->assertInstanceOf(Referral::class, $persisted);
        $this->assertSame($referrer, $persisted->getReferrer());
        $this->assertSame($referred, $persisted->getReferred());
        $this->assertSame($coupon, $persisted->getReferredWelcomeCoupon());
    }
}
