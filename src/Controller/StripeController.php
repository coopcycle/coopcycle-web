<?php

namespace AppBundle\Controller;

use AppBundle\Domain\Order\Event\CheckoutFailed;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\StripeAccount;
use AppBundle\Service\EmailManager;
use AppBundle\Service\OrderManager;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\StripeManager;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\UserBundle\Model\UserManagerInterface;
use Hashids\Hashids;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Psr\Log\LoggerInterface;
use SimpleBus\SymfonyBridge\Bus\EventBus;
use Stripe;
use Stripe\Exception\ApiErrorException;
use Sylius\Component\Payment\Model\PaymentInterface;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @see https://stripe.com/docs/connect/standard-accounts
 */
class StripeController extends AbstractController
{
    private $secret;
    private $debug;
    private $entityManager;
    private $logger;

    public function __construct(
        string $secret,
        bool $debug,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger)
    {
        $this->secret = $secret;
        $this->debug = $debug;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * @Route("/stripe/connect/standard", name="stripe_connect_standard_account")
     */
    public function connectStandardAccountAction(
        Request $request,
        JWTEncoderInterface $jwtEncoder,
        SettingsManager $settingsManager,
        UserManagerInterface $userManager)
    {
        if (!$request->query->has('state')) {
            throw $this->createAccessDeniedException();
        }

        $state = $request->query->get('state');

        try {
            $payload = $jwtEncoder->decode($state);
        } catch (JWTDecodeFailureException $e) {
            throw $this->createAccessDeniedException();
        }

        if (!isset($payload['iss']) || !isset($payload['slm'])) {
            throw $this->createAccessDeniedException();
        }

        $redirect = $payload['iss'];
        $livemode = filter_var($payload['slm'], FILTER_VALIDATE_BOOLEAN);

        $secretKey = $livemode ? $settingsManager->get('stripe_live_secret_key') : $settingsManager->get('stripe_test_secret_key');

        // curl https://connect.stripe.com/oauth/token \
        // -d client_secret=XXX \
        // -d code=AUTHORIZATION_CODE \
        // -d grant_type=authorization_code

        $params = array(
            'grant_type' => 'authorization_code',
            'code' => $request->query->get('code'),
            'client_secret' => $secretKey,
        );

        $req = curl_init('https://connect.stripe.com/oauth/token');
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req, CURLOPT_POST, true);
        curl_setopt($req, CURLOPT_POSTFIELDS, http_build_query($params));

        // TODO: Additional error handling
        $respCode = curl_getinfo($req, CURLINFO_HTTP_CODE);
        $res = json_decode(curl_exec($req), true);
        curl_close($req);

        // Stripe returns a response containing the authentication credentials for the user:
        //
        // {
        //     "token_type": "bearer",
        //     "stripe_publishable_key": "{PUBLISHABLE_KEY}",
        //     "scope": "read_write",
        //     "livemode": false,
        //     "stripe_user_id": "{ACCOUNT_ID}",
        //     "refresh_token": "{REFRESH_TOKEN}",
        //     "access_token": "{ACCESS_TOKEN}"
        // }
        //
        // If there was a problem, we instead return an error:
        //
        // {
        //     "error": "invalid_grant",
        //     "error_description": "Authorization code does not exist: {AUTHORIZATION_CODE}"
        // }

        if (isset($res['error']) && !empty($res['error'])) {
            $this->addFlash(
                'error',
                $res['error_description']
            );

            return $this->redirectToRoute('homepage');
        }

        Stripe\Stripe::setApiKey($secretKey);
        Stripe\Stripe::setApiVersion(StripeManager::STRIPE_API_VERSION);

        $account = Stripe\Account::retrieve($res['stripe_user_id']);

        // FIXME Why is display_name empty sometimes?
        $displayName = !empty($account->display_name) ? $account->display_name : 'N/A';

        $stripeAccount = new StripeAccount();
        $stripeAccount
            ->setType($account->type)
            ->setDisplayName($displayName)
            ->setPayoutsEnabled($account->payouts_enabled)
            ->setStripeUserId($res['stripe_user_id'])
            ->setRefreshToken($res['refresh_token'])
            ->setLivemode($res['livemode'])
            ;

        $this->getUser()->addStripeAccount($stripeAccount);
        $userManager->updateUser($this->getUser());

        $this->addFlash('stripe_account', $stripeAccount->getId());

        return $this->redirect($redirect);
    }

    /**
     * @see https://stripe.com/docs/connect/webhooks
     *
     * @Route("/stripe/webhook", name="stripe_webhook", methods={"POST"})
     */
    public function webhookAction(Request $request,
        StripeManager $stripeManager,
        SettingsManager $settingsManager,
        OrderManager $orderManager,
        EventBus $eventBus,
        EmailManager $emailManager)
    {
        $this->logger->info('Received webhook');

        $stripeManager->configure();

        $payload = $request->getContent();

        $webhookSecret = $settingsManager->get('stripe_webhook_secret');
        $signature = $request->headers->get('stripe-signature');

        // Verify webhook signature and extract the event.
        // See https://stripe.com/docs/webhooks/signatures for more information.

        try {

            // Don't verify signature in debug mode,
            // to allow simple usage of Stripe CLI
            // @see https://stripe.com/docs/connect/webhooks#test-webhooks-locally
            if ($this->debug) {

                $data = json_decode($payload, true);
                $jsonError = json_last_error();
                if (null === $data && JSON_ERROR_NONE !== $jsonError) {
                    $msg = "Invalid payload: {$payload} "
                      . "(json_last_error() was {$jsonError})";

                    throw new Stripe\Exception\UnexpectedValueException($msg);
                }

                $event = Stripe\Event::constructFrom($data);
            } else {
                $event = Stripe\Webhook::constructEvent(
                    $payload, $signature, $webhookSecret
                );
            }

        } catch(Stripe\Exception\UnexpectedValueException $e) {

            $this->logger->error($e->getMessage());

            // Invalid payload.
            return new Response('', 400);
        } catch(Stripe\Exception\SignatureVerificationException $e) {

            $this->logger->error($e->getMessage());

            // Invalid Signature.
            return new Response('', 400);
        }

        if ($event->account) {
            $this->logger->info(sprintf('Received event of type "%s" from account "%s"', $event->type, $event->account));
        } else {
            $this->logger->info(sprintf('Received event of type "%s"', $event->type));
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                return $this->handlePaymentIntentSucceeded($event, $orderManager);
            case 'payment_intent.payment_failed':
                return $this->handlePaymentIntentPaymentFailed($event, $eventBus, $emailManager);
        }

        return new Response('', 200);
    }

    /**
     * @return PaymentInterface|null
     */
    private function findOneByPaymentIntent(Stripe\PaymentIntent $paymentIntent): ?PaymentInterface
    {
        $qb = $this->entityManager->getRepository(PaymentInterface::class)
            ->createQueryBuilder('p')
            ->andWhere('JSON_GET_FIELD_AS_TEXT(p.details, \'payment_intent\') = :payment_intent')
            ->setParameter('payment_intent', $paymentIntent->id);

        return $qb->getQuery()->getOneOrNullResult();
    }

    private function handlePaymentIntentSucceeded(Stripe\Event $event, OrderManager $orderManager): Response
    {
        $paymentIntent = $event->data->object;

        $this->logger->info(sprintf('Payment Intent has id "%s"', $paymentIntent->id));

        $payment = $this->findOneByPaymentIntent($paymentIntent);

        if (null === $payment) {
            $this->logger->error(sprintf('Payment Intent "%s" not found', $paymentIntent->id));

            return new Response('', 200);
        }

        $order = $payment->getOrder();

        // At the moment, we only manage successful intent via webhooks for Giropay
        if ($payment->isGiropay() && PaymentInterface::STATE_PROCESSING === $payment->getState()) {

            // FIXME
            // Here we should check if the time range is still realistic
            // We have no idea when the webhook is called actually

            $orderManager->checkout($order);
            $this->entityManager->flush();
        }

        return new Response('', 200);
    }

    private function handlePaymentIntentPaymentFailed(Stripe\Event $event, EventBus $eventBus, EmailManager $emailManager)
    {
        $paymentIntent = $event->data->object;

        $this->logger->info(sprintf('Payment Intent has id "%s"', $paymentIntent->id));

        $payment = $this->findOneByPaymentIntent($paymentIntent);

        if (null === $payment) {
            $this->logger->error(sprintf('Payment Intent "%s" not found', $paymentIntent->id));

            return new Response('', 200);
        }

        $order = $payment->getOrder();

        // At the moment, we only manage payment failed intent via webhooks for Giropay
        if ($payment->isGiropay() && PaymentInterface::STATE_PROCESSING === $payment->getState()) {

            // This will change payment state to "failed"
            $eventBus->handle(
                new CheckoutFailed($order, $payment, $paymentIntent->last_payment_error->message)
            );
            $this->entityManager->flush();

            $emailManager->sendTo(
                $emailManager->createOrderPaymentFailedMessage($order),
                sprintf('%s <%s>', $order->getCustomer()->getFullName(), $order->getCustomer()->getEmail())
            );
        }

        return new Response('', 200);
    }

    /**
     * @see https://stripe.com/docs/payments/accept-a-payment-synchronously#web-create-payment-intent
     *
     * @Route("/stripe/payment/{hashId}/create-intent", name="stripe_create_payment_intent", methods={"POST"})
     */
    public function createPaymentIntentAction($hashId, Request $request,
        OrderNumberAssignerInterface $orderNumberAssigner,
        StripeManager $stripeManager)
    {
        $hashids = new Hashids($this->secret, 8);

        $decoded = $hashids->decode($hashId);
        if (count($decoded) !== 1) {

            return new JsonResponse(['error' =>
                ['message' => sprintf('Payment with hash "%s" does not exist', $hashId)]
            ], 400);
        }

        $paymentId = current($decoded);

        $payment = $this->entityManager
            ->getRepository(PaymentInterface::class)
            ->find($paymentId);

        if (null === $payment) {

            return new JsonResponse(['error' =>
                ['message' => sprintf('Payment with id "%d" does not exist', $paymentId)]
            ], 400);
        }

        $content = $request->getContent();

        $data = !empty($content) ? json_decode($content, true) : [];

        if (!isset($data['payment_method_id'])) {

            return new JsonResponse(['error' =>
                ['message' => 'No payment_method_id key found in request']
            ], 400);
        }

        $order = $payment->getOrder();

        // Assign order number now because it is needed for Stripe
        $orderNumberAssigner->assignNumber($order);

        $stripeManager->configure();

        try {

            $payment->setPaymentMethod($data['payment_method_id']);

            $intent = $stripeManager->createIntent($payment);
            $payment->setPaymentIntent($intent);

            $this->entityManager->flush();

        } catch (ApiErrorException $e) {

            return new JsonResponse(['error' =>
                ['message' => $e->getMessage()]
            ], 400);
        }

        $this->logger->info(
            sprintf('Order #%d | Created payment intent %s', $order->getId(), $payment->getPaymentIntent())
        );

        $response = [];

        if ($payment->requiresUseStripeSDK()) {

            $this->logger->info(
                sprintf('Order #%d | Payment Intent requires action "%s"', $order->getId(), $payment->getPaymentIntentNextAction())
            );

            $response = [
                'requires_action' => true,
                'payment_intent_client_secret' => $payment->getPaymentIntentClientSecret()
            ];

        // When the status is "succeeded", it means we captured automatically
        // When the status is "requires_capture", it means we separated authorization and capture
        } elseif ('succeeded' === $payment->getPaymentIntentStatus() || $payment->requiresCapture()) {

            $this->logger->info(
                sprintf('Order #%d | Payment Intent status is "%s"', $order->getId(), $payment->getPaymentIntentStatus())
            );

            // The payment didn’t need any additional actions and completed!
            // Handle post-payment fulfillment
            $response = [
                'requires_action' => false,
                'payment_intent' => $payment->getPaymentIntent()
            ];

        } else {

            return new JsonResponse(['error' =>
                ['message' => 'Invalid PaymentIntent status']
            ], 400);
        }

        return new JsonResponse($response);
    }

    /**
     * @see https://stripe.com/docs/payments/giropay/accept-a-payment#create-payment-intent
     *
     * @Route("/stripe/payment/{hashId}/giropay/create-intent", name="stripe_create_giropay_payment_intent", methods={"POST"})
     */
    public function createGiropayPaymentIntentAction($hashId, Request $request,
        OrderNumberAssignerInterface $orderNumberAssigner,
        StripeManager $stripeManager)
    {
        $hashids = new Hashids($this->secret, 8);

        $decoded = $hashids->decode($hashId);
        if (count($decoded) !== 1) {

            return new JsonResponse(['error' =>
                ['message' => sprintf('Payment with hash "%s" does not exist', $hashId)]
            ], 400);
        }

        $paymentId = current($decoded);

        $payment = $this->entityManager
            ->getRepository(PaymentInterface::class)
            ->find($paymentId);

        if (null === $payment) {

            return new JsonResponse(['error' =>
                ['message' => sprintf('Payment with id "%d" does not exist', $paymentId)]
            ], 400);
        }

        $order = $payment->getOrder();

        // Assign order number now because it is needed for Stripe
        $orderNumberAssigner->assignNumber($order);

        try {

            $payment->setState(PaymentInterface::STATE_PROCESSING);
            $payment->setPaymentMethodTypes(['giropay']);

            $intent = $stripeManager->createGiropayIntent($payment);
            $payment->setPaymentIntent($intent);

            $this->entityManager->flush();

        } catch (ApiErrorException $e) {

            return new JsonResponse(['error' =>
                ['message' => $e->getMessage()]
            ], 400);
        }

        $this->logger->info(
            sprintf('Order #%d | Created payment intent %s', $order->getId(), $payment->getPaymentIntent())
        );

        $returnUrl = $this->generateUrl('payment_confirm', [
            'hashId' => $hashids->encode($payment->getId()),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse([
            'payment_intent_client_secret' => $payment->getPaymentIntentClientSecret(),
            'return_url' => $returnUrl,
        ]);
    }
}
