<?php

namespace AppBundle\Controller;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\StripeAccount;
use AppBundle\Service\SettingsManager;
use AppBundle\Service\StripeManager;
use FOS\UserBundle\Model\UserManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Stripe;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @see https://stripe.com/docs/connect/standard-accounts
 */
class StripeController extends AbstractController
{
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
    public function webhookAction(Request $request, StripeManager $stripeManager, SettingsManager $settingsManager)
    {
        $this->logger->info('Received webhook');

        $stripeManager->configure();

        $payload = $request->getContent();

        $webhookSecret = $settingsManager->get('stripe_webhook_secret');
        $signature = $request->headers->get('stripe-signature');

        // Verify webhook signature and extract the event.
        // See https://stripe.com/docs/webhooks/signatures for more information.

        try {

            $event = Stripe\Webhook::constructEvent(
                $payload, $signature, $webhookSecret
            );

        } catch(\UnexpectedValueException $e) {

            $this->logger->error($e->getMessage());

            // Invalid payload.
            return new Response('', 400);
        } catch(Stripe\Exception\SignatureVerificationException $e) {

            $this->logger->error($e->getMessage());

            // Invalid Signature.
            return new Response('', 400);
        }

        $this->logger->info(sprintf('Received event of type "%s"', $event->type));

        return new Response('', 200);
    }
}
