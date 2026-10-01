<?php

namespace AppBundle\Controller\Admin;

use AppBundle\Entity\Referral\ReferralLevel;
use AppBundle\Entity\Referral\ReferralRepository;
use AppBundle\Form\Referral\ReferralLevelType;
use AppBundle\Form\Referral\ReferralWelcomeSettingsType;
use AppBundle\Service\Referral\ReferralLevelResolver;
use AppBundle\Service\SettingsManager;
use AppBundle\Sylius\Promotion\Action\FixedDiscountPromotionActionCommand;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReferralProgramController extends AbstractController
{
    private const ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly bool $referralProgramEnabled,
        private readonly SettingsManager $settingsManager,
        private readonly ReferralRepository $referralRepository,
        private readonly ReferralLevelResolver $referralLevelResolver,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('/admin/referral-program', name: 'admin_referral_program', methods: ['GET', 'POST'])]
    public function __invoke(Request $request, PaginatorInterface $paginator): Response
    {
        if (!$this->referralProgramEnabled) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $levels = $this->entityManager->getRepository(ReferralLevel::class)->findBy([], ['position' => 'ASC']);

        $levelsFormBuilder = $this->createFormBuilder(null, ['data_class' => null]);
        foreach ($levels as $level) {
            $levelsFormBuilder->add('level_' . $level->getId(), ReferralLevelType::class, [
                'data' => $level,
                'label' => false,
            ]);
        }
        $levelsForm = $levelsFormBuilder->getForm();
        $levelsForm->handleRequest($request);

        if ($levelsForm->isSubmitted() && $levelsForm->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('notice', 'referral.levels.saved');

            return $this->redirectToRoute('admin_referral_program');
        }

        $welcomeSettingsForm = $this->createForm(ReferralWelcomeSettingsType::class, [
            'referral_welcome_reward_type' => $this->settingsManager->get('referral_welcome_reward_type') ?: FixedDiscountPromotionActionCommand::TYPE,
            'referral_welcome_reward_amount' => $this->settingsManager->get('referral_welcome_reward_amount'),
            'referral_welcome_reward_percentage' => $this->settingsManager->get('referral_welcome_reward_percentage'),
            'referral_welcome_coupon_validity_days' => (int) ($this->settingsManager->get('referral_welcome_coupon_validity_days') ?: 30),
            'referral_pending_ttl_days' => (int) ($this->settingsManager->get('referral_pending_ttl_days') ?: 30),
        ]);
        $welcomeSettingsForm->handleRequest($request);

        if ($welcomeSettingsForm->isSubmitted() && $welcomeSettingsForm->isValid()) {
            foreach ($welcomeSettingsForm->getData() as $key => $value) {
                $this->settingsManager->set($key, (string) $value);
            }
            $this->settingsManager->flush();
            $this->addFlash('notice', 'referral.welcome_settings.saved');

            return $this->redirectToRoute('admin_referral_program');
        }

        $statusCounts = $this->referralRepository->getStatusCounts();
        $convertible = $statusCounts['completed'] + $statusCounts['expired'];

        $levelBreakdown = [];
        foreach ($levels as $level) {
            $levelBreakdown[$level->getName()] = 0;
        }
        foreach ($this->referralRepository->findReferrersWithCompletedReferral() as $referrer) {
            $level = $this->referralLevelResolver->resolve($referrer->getSuccessfulReferralCount());
            if (null !== $level) {
                $levelBreakdown[$level->getName()] = ($levelBreakdown[$level->getName()] ?? 0) + 1;
            }
        }

        $activeStatus = $request->query->get('status');
        if (!in_array($activeStatus, ['pending', 'completed', 'expired'], true)) {
            $activeStatus = null;
        }

        $referrals = $paginator->paginate(
            $this->referralRepository->createListQueryBuilder($activeStatus),
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE
        );

        return $this->render('admin/referral_program.html.twig', [
            'levels' => $levels,
            'levels_form' => $levelsForm,
            'welcome_settings_form' => $welcomeSettingsForm,
            'status_counts' => $statusCounts,
            'conversion_rate' => $convertible > 0 ? $statusCounts['completed'] / $convertible : null,
            'level_breakdown' => $levelBreakdown,
            'active_status' => $activeStatus,
            'referrals' => $referrals,
        ]);
    }
}
