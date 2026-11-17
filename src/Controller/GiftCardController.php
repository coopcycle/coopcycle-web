<?php

namespace AppBundle\Controller;

use AppBundle\Entity\GiftCard\GiftCard;
use AppBundle\Entity\GiftCard\GiftCardRepository;
use AppBundle\Form\GiftCard\GiftCardPurchaseType;
use AppBundle\Service\GiftCard\GiftCardIssuer;
use AppBundle\Service\GiftCard\GiftCardPurchaseManager;
use AppBundle\Service\GiftCard\GiftCardStatus;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use Hashids\Hashids;
use Stripe;
use Sylius\Component\Currency\Context\CurrencyContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Buying a gift card.
 *
 * Deliberately nothing to do with the cart or the checkout: a purchase is
 * not an order, and keeping the two apart is what stops a gift card sale
 * being counted as turnover before the card has bought anything.
 */
class GiftCardController extends AbstractController
{
    public function __construct(
        private readonly bool $giftCardsEnabled,
        private readonly GiftCardStatus $giftCardStatus,
        private readonly GiftCardRepository $giftCardRepository,
        private readonly GiftCardPurchaseManager $purchaseManager,
        private readonly GiftCardIssuer $giftCardIssuer,
        private readonly SettingsManager $settingsManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly Hashids $hashids12,
    ) {
    }

    #[Route('/gift-cards', name: 'gift_cards', methods: ['GET'])]
    public function indexAction(Request $request): Response
    {
        $this->denyUnlessActive();

        $form = $this->createForm(GiftCardPurchaseType::class, [
            'purchaserEmail' => $this->getUser()?->getEmail(),
        ]);

        return $this->render('gift_card/purchase.html.twig', [
            'form' => $form,
            'denominations' => $this->giftCardStatus->getDenominations(),
            'min_amount' => $this->giftCardStatus->getMinAmount(),
            'max_amount' => $this->giftCardStatus->getMaxAmount(),
            'legal_mention' => $this->giftCardStatus->getLegalMention(),
            'stripe_publishable_key' => $this->settingsManager->get('stripe_publishable_key'),
            'expires_at' => $this->giftCardStatus->resolveExpiresAt(),
        ]);
    }

    /**
     * Step one: create the card, then charge for it.
     *
     * The card row is written before Stripe is called so its id can be the
     * idempotency key -- a customer who double-clicks gets one charge, not
     * two.
     */
    #[Route('/gift-cards/purchase', name: 'gift_card_purchase', methods: ['POST'])]
    public function purchaseAction(
        Request $request,
        CurrencyContextInterface $currencyContext,
        TranslatorInterface $translator
    ): JsonResponse {
        $this->denyUnlessActive();

        $form = $this->createForm(GiftCardPurchaseType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return new JsonResponse([
                'error' => $this->firstError($form) ?? $translator->trans('gift_card.purchase.invalid'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $paymentMethodId = $request->request->get('paymentMethodId');

        if (empty($paymentMethodId)) {
            return new JsonResponse([
                'error' => $translator->trans('gift_card.purchase.no_payment_method'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = $form->getData();

        $giftCard = $this->giftCardIssuer->createPending(
            amount: (int) $data['amount'],
            currencyCode: $currencyContext->getCurrencyCode(),
            purchaser: $this->getUser()?->getCustomer(),
            purchaserEmail: $data['purchaserEmail'],
            recipientEmail: $data['recipientEmail'] ?? null,
            recipientName: $data['recipientName'] ?? null,
            message: $data['message'] ?? null,
            restaurant: $data['restaurant'] ?? null,
        );

        try {
            $intent = $this->purchaseManager->createIntent($giftCard, $paymentMethodId);
        } catch (Stripe\Exception\ApiErrorException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->intentResponse($giftCard, $intent, $translator);
    }

    /**
     * Step two, only reached when the bank asked the customer to confirm.
     */
    #[Route('/gift-cards/purchase/confirm', name: 'gift_card_purchase_confirm', methods: ['POST'])]
    public function confirmAction(Request $request, TranslatorInterface $translator): JsonResponse
    {
        $this->denyUnlessActive();

        $giftCard = $this->giftCardRepository->find((int) $request->request->get('giftCardId'));

        if (null === $giftCard) {
            throw $this->createNotFoundException();
        }

        try {
            $intent = $this->purchaseManager->confirm($giftCard);
        } catch (Stripe\Exception\ApiErrorException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->intentResponse($giftCard, $intent, $translator);
    }

    #[Route('/gift-cards/confirmation/{hashId}', name: 'gift_card_confirmation', methods: ['GET'])]
    public function confirmationAction(string $hashId): Response
    {
        $this->denyUnlessActive();

        $giftCard = $this->resolveByHashId($hashId);

        // Never tell someone their card is ready while it is still waiting
        // on a payment. A pending card has no balance and its code buys
        // nothing.
        if (GiftCard::STATE_PENDING === $giftCard->getState()) {
            throw $this->createNotFoundException();
        }

        return $this->render('gift_card/confirmation.html.twig', [
            'gift_card' => $giftCard,
            'legal_mention' => $this->giftCardStatus->getLegalMention(),
        ]);
    }

    private function intentResponse(
        GiftCard $giftCard,
        Stripe\PaymentIntent $intent,
        TranslatorInterface $translator
    ): JsonResponse {
        if (Stripe\PaymentIntent::STATUS_REQUIRES_ACTION === $intent->status) {
            return new JsonResponse([
                'requiresAction' => true,
                'clientSecret' => $intent->client_secret,
                'giftCardId' => $giftCard->getId(),
            ]);
        }

        if (Stripe\PaymentIntent::STATUS_SUCCEEDED !== $intent->status) {
            return new JsonResponse([
                'error' => $translator->trans('gift_card.purchase.failed'),
            ], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'redirectUrl' => $this->generateUrl('gift_card_confirmation', [
                'hashId' => $this->hashids12->encode($giftCard->getId()),
            ]),
        ]);
    }

    private function resolveByHashId(string $hashId): GiftCard
    {
        $decoded = $this->hashids12->decode($hashId);

        if (count($decoded) !== 1) {
            throw $this->createNotFoundException();
        }

        $giftCard = $this->giftCardRepository->find($decoded[0]);

        if (null === $giftCard) {
            throw $this->createNotFoundException();
        }

        return $giftCard;
    }

    private function firstError($form): ?string
    {
        foreach ($form->getErrors(true) as $error) {
            return $error->getMessage();
        }

        return null;
    }

    private function denyUnlessActive(): void
    {
        if (!$this->giftCardsEnabled || !$this->giftCardStatus->isActive()) {
            throw $this->createNotFoundException();
        }
    }
}
