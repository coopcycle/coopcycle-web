<?php

namespace AppBundle\Service\Referral;

use AppBundle\Entity\Referral\Referral;
use AppBundle\Entity\Referral\ReferralRepository;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\User;
use AppBundle\Service\EmailManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * Orchestrates referral capture at signup. Deliberately tolerant of bad
 * input (unknown/expired code, self-referral, double submit) -- none of
 * these should ever block account creation, they should just result in no
 * referral being recorded.
 */
class ReferralManager
{
    public function __construct(
        private readonly RepositoryInterface $customerRepository,
        private readonly ReferralRepository $referralRepository,
        private readonly ReferralCodeGenerator $referralCodeGenerator,
        private readonly ReferralRewardCouponFactory $referralRewardCouponFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailManager $emailManager,
        private readonly ReferralProgramStatus $referralProgramStatus,
        private readonly LoggerInterface $logger)
    {
    }

    public function registerPendingReferral(User $newUser, ?string $referralCode): void
    {
        if (!$this->referralProgramStatus->isActive()) {
            return;
        }

        $referred = $newUser->getCustomer();

        if (null === $referred) {
            return;
        }

        // Every new customer gets their own shareable code, whether or not
        // they were referred themselves.
        $this->referralCodeGenerator->generateFor($referred);

        if (empty($referralCode)) {
            $this->entityManager->flush();

            return;
        }

        $referrer = $this->customerRepository->findOneBy(['referralCode' => strtoupper(trim($referralCode))]);

        if (!$referrer instanceof Customer) {
            $this->logger->info(sprintf('Referral code "%s" not found, ignoring', $referralCode));
            $this->entityManager->flush();

            return;
        }

        if ($referrer === $referred) {
            $this->entityManager->flush();

            return;
        }

        if (null !== $this->referralRepository->findPendingByReferredCustomer($referred)) {
            $this->entityManager->flush();

            return;
        }

        $referral = new Referral();
        $referral->setReferrer($referrer);
        $referral->setReferred($referred);
        $referral->setReferredWelcomeCoupon(
            $this->referralRewardCouponFactory->createReferredWelcomeCoupon($referred)
        );

        $this->entityManager->persist($referral);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            // Defense in depth against a double-submit race -- the DB unique
            // index on referred_id is the actual guarantee that a customer
            // can only ever be referred once.
            $this->logger->info(sprintf(
                'Referral for customer #%d already exists, ignoring duplicate',
                $referred->getId()
            ));

            return;
        }

        $this->emailManager->sendTo(
            $this->emailManager->createReferralWelcomeMessage($referral),
            $referred->getEmail()
        );
    }
}
