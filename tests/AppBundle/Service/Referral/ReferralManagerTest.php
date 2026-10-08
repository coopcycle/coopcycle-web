<?php

namespace Tests\AppBundle\Service\Referral;

use AppBundle\Entity\Referral\Referral;
use AppBundle\Entity\Referral\ReferralRepository;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\User;
use AppBundle\Service\EmailManager;
use AppBundle\Service\Referral\ReferralCodeGenerator;
use AppBundle\Service\Referral\ReferralEmailCanonizerFactory;
use AppBundle\Service\Referral\ReferralManager;
use AppBundle\Service\Referral\ReferralProgramStatus;
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
    private $emailManager;
    private $referralProgramStatus;
    private $logger;
    private $manager;

    protected function setUp(): void
    {
        $this->customerRepository = $this->prophesize(RepositoryInterface::class);
        // No pre-existing account shares the new signup's canonical email,
        // unless a test says otherwise.
        $this->customerRepository->findBy(Argument::any())->willReturn([]);
        $this->referralRepository = $this->prophesize(ReferralRepository::class);
        $this->referralCodeGenerator = $this->prophesize(ReferralCodeGenerator::class);
        $this->referralRewardCouponFactory = $this->prophesize(ReferralRewardCouponFactory::class);
        $this->entityManager = $this->prophesize(EntityManagerInterface::class);
        $this->emailManager = $this->prophesize(EmailManager::class);
        $this->referralProgramStatus = $this->prophesize(ReferralProgramStatus::class);
        $this->referralProgramStatus->isActive()->willReturn(true);
        $this->logger = $this->prophesize(LoggerInterface::class);

        // The real canonizer, built exactly as the container builds it --
        // it's a pure value transformation, and it's the thing under test below.
        $emailCanonizer = ReferralEmailCanonizerFactory::create();

        $this->manager = new ReferralManager(
            $this->customerRepository->reveal(),
            $this->referralRepository->reveal(),
            $this->referralCodeGenerator->reveal(),
            $this->referralRewardCouponFactory->reveal(),
            $this->entityManager->reveal(),
            $this->emailManager->reveal(),
            $this->referralProgramStatus->reveal(),
            $emailCanonizer,
            $this->logger->reveal()
        );
    }

    private function buildUserWithCustomer(?string $email = null): array
    {
        $customer = new Customer();
        if (null !== $email) {
            $customer->setEmail($email);
        }
        $user = new User();
        $user->setCustomer($customer);

        return [$user, $customer];
    }

    public function testDoesNothingAtAllWhenProgramIsNotActive(): void
    {
        [$user, $customer] = $this->buildUserWithCustomer();

        $this->referralProgramStatus->isActive()->willReturn(false);

        $this->referralCodeGenerator->generateFor(Argument::any())->shouldNotBeCalled();
        $this->entityManager->flush()->shouldNotBeCalled();

        $this->manager->registerPendingReferral($user, 'abc123');
    }

    public function testGeneratesOwnCodeAndDoesNothingElseWhenNoCodeGiven(): void
    {
        [$user, $customer] = $this->buildUserWithCustomer();

        $this->referralCodeGenerator->generateFor($customer)->shouldBeCalledOnce();
        $this->referralRepository->findPendingByReferredCustomer($customer)->shouldNotBeCalled();
        $this->entityManager->persist(Argument::type(Referral::class))->shouldNotBeCalled();
        $this->entityManager->flush()->shouldBeCalledOnce();
        $this->emailManager->sendTo(Argument::cetera())->shouldNotBeCalled();

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

    public function selfReferralByAliasProvider(): array
    {
        return [
            'plus alias' => ['foo@example.com', 'foo+alias@example.com'],
            'plus alias, different case' => ['foo@example.com', 'FOO+Alias@Example.com'],
            'gmail dots' => ['foo@gmail.com', 'f.o.o@gmail.com'],
            'googlemail alias of a gmail address' => ['foo@gmail.com', 'foo+alias@googlemail.com'],
        ];
    }

    /**
     * Registering under an alias of your own address is still perfectly fine
     * -- it just must not earn you a referral reward for yourself.
     *
     * @dataProvider selfReferralByAliasProvider
     */
    public function testIgnoresSelfReferralByEmailAlias(string $referrerEmail, string $referredEmail): void
    {
        [$user, $referred] = $this->buildUserWithCustomer($referredEmail);

        $referrer = new Customer();
        $referrer->setEmail($referrerEmail);

        $this->referralCodeGenerator->generateFor($referred)->shouldBeCalledOnce();
        $this->customerRepository->findOneBy(['referralCode' => 'ABC123'])->willReturn($referrer);

        $this->entityManager->persist(Argument::type(Referral::class))->shouldNotBeCalled();
        $this->entityManager->flush()->shouldBeCalledOnce();
        $this->emailManager->sendTo(Argument::cetera())->shouldNotBeCalled();

        $this->manager->registerPendingReferral($user, 'abc123');
    }

    /**
     * Farming the program by signing up a second time under an alias of your
     * own address, so a friend's code earns you both something.
     */
    public function testIgnoresReferralWhenTheNewAccountAliasesAnExistingOne(): void
    {
        [$user, $referred] = $this->buildUserWithCustomer('alice+new@example.com');

        $referrer = new Customer();
        $referrer->setEmail('bob@example.com');

        $existingAccount = new Customer();
        $existingAccount->setEmail('alice@example.com');

        $this->referralCodeGenerator->generateFor($referred)->shouldBeCalledOnce();
        $this->customerRepository->findOneBy(['referralCode' => 'ABC123'])->willReturn($referrer);
        $this->customerRepository->findBy(['referralCanonicalEmail' => 'alice@example.com'])
            ->willReturn([$existingAccount]);

        $this->entityManager->persist(Argument::type(Referral::class))->shouldNotBeCalled();
        $this->entityManager->flush()->shouldBeCalledOnce();
        $this->emailManager->sendTo(Argument::cetera())->shouldNotBeCalled();

        $this->manager->registerPendingReferral($user, 'abc123');
    }

    /**
     * The new account matching only itself is the normal case -- it has
     * already been persisted (and so canonicalized) by the time a referral is
     * registered for it.
     */
    public function testAcceptsReferralWhenTheOnlyCanonicalMatchIsTheNewAccountItself(): void
    {
        [$user, $referred] = $this->buildUserWithCustomer('carol@example.com');

        $referrer = new Customer();
        $referrer->setEmail('bob@example.com');

        $this->referralCodeGenerator->generateFor($referred)->shouldBeCalledOnce();
        $this->customerRepository->findOneBy(['referralCode' => 'ABC123'])->willReturn($referrer);
        $this->customerRepository->findBy(['referralCanonicalEmail' => 'carol@example.com'])
            ->willReturn([$referred]);
        $this->referralRepository->findPendingByReferredCustomer($referred)->willReturn(null);

        $coupon = $this->prophesize(PromotionCouponInterface::class)->reveal();
        $this->referralRewardCouponFactory->createReferredWelcomeCoupon($referred)->willReturn($coupon);

        $this->entityManager->persist(Argument::type(Referral::class))->shouldBeCalledOnce();
        $this->entityManager->flush()->shouldBeCalledOnce();

        $welcomeMessage = new \Symfony\Component\Mime\Email();
        $this->emailManager->createReferralWelcomeMessage(Argument::type(Referral::class))->willReturn($welcomeMessage);
        $this->emailManager->sendTo($welcomeMessage, 'carol@example.com')->shouldBeCalledOnce();

        $this->manager->registerPendingReferral($user, 'abc123');
    }

    public function testStillAcceptsAReferralBetweenTwoDistinctMailboxes(): void
    {
        [$user, $referred] = $this->buildUserWithCustomer('bob@example.com');

        $referrer = new Customer();
        $referrer->setEmail('alice@example.com');

        $this->referralCodeGenerator->generateFor($referred)->shouldBeCalledOnce();
        $this->customerRepository->findOneBy(['referralCode' => 'ABC123'])->willReturn($referrer);
        $this->referralRepository->findPendingByReferredCustomer($referred)->willReturn(null);

        $coupon = $this->prophesize(PromotionCouponInterface::class)->reveal();
        $this->referralRewardCouponFactory->createReferredWelcomeCoupon($referred)->willReturn($coupon);

        $this->entityManager->persist(Argument::type(Referral::class))->shouldBeCalledOnce();
        $this->entityManager->flush()->shouldBeCalledOnce();

        $welcomeMessage = new \Symfony\Component\Mime\Email();
        $this->emailManager->createReferralWelcomeMessage(Argument::type(Referral::class))->willReturn($welcomeMessage);
        $this->emailManager->sendTo($welcomeMessage, 'bob@example.com')->shouldBeCalledOnce();

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

        $welcomeMessage = new \Symfony\Component\Mime\Email();
        $this->emailManager->createReferralWelcomeMessage(Argument::type(Referral::class))->willReturn($welcomeMessage);
        $this->emailManager->sendTo($welcomeMessage, $referred->getEmail())->shouldBeCalledOnce();

        $this->manager->registerPendingReferral($user, 'abc123');

        $this->assertInstanceOf(Referral::class, $persisted);
        $this->assertSame($referrer, $persisted->getReferrer());
        $this->assertSame($referred, $persisted->getReferred());
        $this->assertSame($coupon, $persisted->getReferredWelcomeCoupon());
    }
}
