<?php

namespace AppBundle\Controller\Admin;

use AppBundle\Entity\GiftCard\GiftCard;
use AppBundle\Entity\GiftCard\GiftCardRepository;
use AppBundle\Entity\GiftCard\GiftCardTransactionRepository;
use AppBundle\Form\GiftCard\GiftCardIssueType;
use AppBundle\Form\GiftCard\GiftCardSettingsType;
use AppBundle\Service\GiftCard\GiftCardIssuer;
use AppBundle\Service\GiftCard\GiftCardStatus;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class GiftCardController extends AbstractController
{
    private const ITEMS_PER_PAGE = 20;

    public function __construct(
        private readonly bool $giftCardsEnabled,
        private readonly GiftCardStatus $giftCardStatus,
        private readonly SettingsManager $settingsManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly GiftCardRepository $giftCardRepository,
        private readonly GiftCardTransactionRepository $transactionRepository,
    ) {
    }

    #[Route('/admin/gift-cards', name: 'admin_gift_cards', methods: ['GET', 'POST'])]
    public function indexAction(
        Request $request,
        PaginatorInterface $paginator,
        TranslatorInterface $translator
    ): Response {
        $this->denyUnlessEnabled();

        // Independent of the env-var gate (which decides whether this screen
        // exists at all): this is the runtime switch every customer-facing
        // and money-moving path checks, via GiftCardStatus::isActive().
        // Defaults to off, so turning the env var on can't put a live,
        // unconfigured programme -- no amounts set, no legal mention
        // written -- in front of customers.
        $activeForm = $this->container->get('form.factory')->createNamedBuilder(
            'gift_cards_active_form',
            FormType::class,
            [
                'active' => $this->settingsManager->getBoolean('gift_cards_active'),
                'scoping' => $this->settingsManager->getBoolean('gift_card_scoping_enabled'),
            ],
            ['data_class' => null]
        )
            ->add('active', CheckboxType::class, ['required' => false, 'label' => false])
            ->add('scoping', CheckboxType::class, ['required' => false, 'label' => false])
            ->getForm();
        $activeForm->handleRequest($request);

        if ($activeForm->isSubmitted()) {
            $active = (bool) $activeForm->get('active')->getData();
            $scoping = (bool) $activeForm->get('scoping')->getData();

            $this->settingsManager->set('gift_cards_active', $active ? '1' : '0');
            $this->settingsManager->set('gift_card_scoping_enabled', $scoping ? '1' : '0');
            $this->settingsManager->flush();

            $this->addFlash('notice', $translator->trans(
                $active ? 'gift_card.program.activated' : 'gift_card.program.deactivated'
            ));

            return $this->redirectToRoute('admin_gift_cards');
        }

        $settingsForm = $this->createForm(GiftCardSettingsType::class, [
            'gift_card_min_amount' => $this->giftCardStatus->getMinAmount(),
            'gift_card_max_amount' => $this->giftCardStatus->getMaxAmount(),
            'gift_card_denominations' => $this->settingsManager->get('gift_card_denominations') ?? '',
            'gift_card_expiry_policy' => $this->giftCardStatus->getExpiryPolicy(),
            'gift_card_validity_months' => (int) ($this->settingsManager->get('gift_card_validity_months')
                ?? GiftCardStatus::DEFAULT_VALIDITY_MONTHS),
            'gift_card_legal_mention' => $this->settingsManager->get('gift_card_legal_mention') ?? '',
        ]);
        $settingsForm->handleRequest($request);

        if ($settingsForm->isSubmitted() && $settingsForm->isValid()) {
            foreach ($settingsForm->getData() as $key => $value) {
                $this->settingsManager->set($key, (string) $value);
            }
            $this->settingsManager->flush();
            $this->addFlash('notice', $translator->trans('gift_card.settings.saved'));

            return $this->redirectToRoute('admin_gift_cards');
        }

        $state = $request->query->get('state');
        if (!in_array($state, $this->states(), true)) {
            $state = null;
        }

        $giftCards = $paginator->paginate(
            $this->giftCardRepository->createAdminListQueryBuilder($request->query->get('q'), $state),
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE
        );

        return $this->render('admin/gift_cards.html.twig', [
            'active_form' => $activeForm,
            'settings_form' => $settingsForm,
            'is_active' => $this->giftCardStatus->isActive(),
            'gift_cards' => $giftCards,
            'states' => $this->states(),
            'active_state' => $state,
            'query' => $request->query->get('q'),
            // What the coop still owes the people holding its cards. Under
            // multi-purpose voucher treatment this is a liability, not
            // turnover, and somebody will be asked for it at period end.
            'outstanding_liability' => $this->giftCardRepository->getOutstandingLiability(),
        ]);
    }

    #[Route('/admin/gift-cards/new', name: 'admin_gift_card_new', methods: ['GET', 'POST'])]
    public function newAction(
        Request $request,
        GiftCardIssuer $giftCardIssuer,
        CurrencyContextInterface $currencyContext,
        TranslatorInterface $translator
    ): Response {
        $this->denyUnlessEnabled();

        $form = $this->createForm(GiftCardIssueType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $giftCard = $giftCardIssuer->issueGranted(
                amount: (int) $data['amount'],
                currencyCode: $currencyContext->getCurrencyCode(),
                recipientEmail: $data['recipientEmail'] ?? null,
                recipientName: $data['recipientName'] ?? null,
                message: $data['message'] ?? null,
                restaurant: $data['restaurant'] ?? null,
                reason: $data['reason'] ?? null,
            );

            $this->addFlash('notice', $translator->trans('gift_card.issue.created', [
                '%code%' => $giftCard->getCode(),
            ]));

            return $this->redirectToRoute('admin_gift_card', ['id' => $giftCard->getId()]);
        }

        return $this->render('admin/gift_card_new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/admin/gift-cards/{id}', name: 'admin_gift_card', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detailAction(
        int $id,
        Request $request,
        PaginatorInterface $paginator
    ): Response {
        $this->denyUnlessEnabled();

        $giftCard = $this->giftCardRepository->find($id);

        if (null === $giftCard) {
            throw $this->createNotFoundException();
        }

        $history = $paginator->paginate(
            $this->transactionRepository->createHistoryQueryBuilder($giftCard),
            $request->query->getInt('page', 1),
            self::ITEMS_PER_PAGE
        );

        return $this->render('admin/gift_card.html.twig', [
            'gift_card' => $giftCard,
            'history' => $history,
            // Shown side by side with the stored balance on purpose: if the
            // two ever disagree, the person looking at this screen is the
            // one who needs to know first.
            'ledger_balance' => $this->transactionRepository->getLedgerBalance($giftCard),
        ]);
    }

    #[Route('/admin/gift-cards/{id}/void', name: 'admin_gift_card_void', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function voidAction(int $id, Request $request, TranslatorInterface $translator): Response
    {
        $this->denyUnlessEnabled();

        if (!$this->isCsrfTokenValid('admin_gift_card_void', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $giftCard = $this->giftCardRepository->find($id);

        if (null === $giftCard) {
            throw $this->createNotFoundException();
        }

        $giftCard->cancel();
        $this->entityManager->flush();

        $this->addFlash('notice', $translator->trans('gift_card.void.done', [
            '%code%' => $giftCard->getCode(),
        ]));

        return $this->redirectToRoute('admin_gift_card', ['id' => $giftCard->getId()]);
    }

    private function denyUnlessEnabled(): void
    {
        if (!$this->giftCardsEnabled) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('ROLE_ADMIN');
    }

    private function states(): array
    {
        return [
            GiftCard::STATE_ACTIVE,
            GiftCard::STATE_DEPLETED,
            GiftCard::STATE_EXPIRED,
            GiftCard::STATE_CANCELLED,
            GiftCard::STATE_FROZEN,
            GiftCard::STATE_PENDING,
        ];
    }
}
