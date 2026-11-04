<?php

namespace AppBundle\Controller\Admin;

use AppBundle\Entity\Loyalty\LoyaltyReward;
use AppBundle\Form\Loyalty\LoyaltyRewardType;
use AppBundle\Form\Loyalty\LoyaltySettingsType;
use AppBundle\Service\Loyalty\LoyaltyPointsManager;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LoyaltyProgramController extends AbstractController
{
    public function __construct(
        private readonly bool $loyaltyProgramEnabled,
        private readonly SettingsManager $settingsManager,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('/admin/loyalty-program', name: 'admin_loyalty_program', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        if (!$this->loyaltyProgramEnabled) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Independent of $loyaltyProgramEnabled (the env-var gate controlling
        // whether this dashboard exists at all): this is the runtime switch
        // that points-granting and customer-facing paths check, via
        // LoyaltyProgramStatus::isActive(). Defaults to off, so an admin has
        // to deliberately turn the program on once rewards are configured.
        $activeForm = $this->container->get('form.factory')->createNamedBuilder(
            'loyalty_program_active_form',
            FormType::class,
            ['active' => $this->settingsManager->getBoolean('loyalty_program_active')],
            ['data_class' => null]
        )
            ->add('active', CheckboxType::class, ['required' => false, 'label' => false])
            ->getForm();
        $activeForm->handleRequest($request);

        if ($activeForm->isSubmitted()) {
            $active = (bool) $activeForm->get('active')->getData();
            $this->settingsManager->set('loyalty_program_active', $active ? '1' : '0');
            $this->settingsManager->flush();
            $this->addFlash('notice', $active ? 'loyalty.program.activated' : 'loyalty.program.deactivated');

            return $this->redirectToRoute('admin_loyalty_program');
        }

        $settingsForm = $this->createForm(LoyaltySettingsType::class, [
            'loyalty_points_per_currency_unit' => (int) ($this->settingsManager->get('loyalty_points_per_currency_unit')
                ?? LoyaltyPointsManager::DEFAULT_POINTS_PER_CURRENCY_UNIT),
            'loyalty_points_validity_days' => (int) ($this->settingsManager->get('loyalty_points_validity_days')
                ?? LoyaltyPointsManager::DEFAULT_POINTS_VALIDITY_DAYS),
        ]);
        $settingsForm->handleRequest($request);

        if ($settingsForm->isSubmitted() && $settingsForm->isValid()) {
            foreach ($settingsForm->getData() as $key => $value) {
                $this->settingsManager->set($key, (string) $value);
            }
            $this->settingsManager->flush();
            $this->addFlash('notice', 'loyalty.settings.saved');

            return $this->redirectToRoute('admin_loyalty_program');
        }

        $rewardRepository = $this->entityManager->getRepository(LoyaltyReward::class);

        $rewardsForm = $this->createFormBuilder(
            ['rewards' => $rewardRepository->findBy([], ['position' => 'ASC', 'pointsCost' => 'ASC'])],
            ['data_class' => null]
        )
            ->add('rewards', CollectionType::class, [
                'entry_type' => LoyaltyRewardType::class,
                'label' => false,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
            ])
            ->getForm();
        $rewardsForm->handleRequest($request);

        if ($rewardsForm->isSubmitted() && $rewardsForm->isValid()) {
            $submitted = $rewardsForm->get('rewards')->getData();

            foreach ($rewardRepository->findAll() as $existing) {
                if (!in_array($existing, $submitted, true)) {
                    $this->entityManager->remove($existing);
                }
            }

            foreach ($submitted as $position => $reward) {
                $reward->setPosition($position);
                $this->entityManager->persist($reward);
            }

            $this->entityManager->flush();
            $this->addFlash('notice', 'loyalty.rewards.saved');

            return $this->redirectToRoute('admin_loyalty_program');
        }

        return $this->render('admin/loyalty_program.html.twig', [
            'active_form' => $activeForm,
            'is_active' => $this->settingsManager->getBoolean('loyalty_program_active'),
            'settings_form' => $settingsForm,
            'rewards_form' => $rewardsForm,
        ]);
    }
}
