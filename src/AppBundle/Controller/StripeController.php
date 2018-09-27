<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\StripeAccount;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Stripe;
use Symfony\Component\HttpFoundation\Request;

/**
 * @see https://stripe.com/docs/connect/standard-accounts
 */
class StripeController extends Controller
{
    /**
     * @Route("/stripe/connect/standard", name="stripe_connect_standard_account")
     */
    public function connectStandardAccountAction(Request $request)
    {
        // curl https://connect.stripe.com/oauth/token \
        // -d client_secret=XXX \
        // -d code=AUTHORIZATION_CODE \
        // -d grant_type=authorization_code

        $settingsManager = $this->get('coopcycle.settings_manager');

        $params = array(
            'grant_type' => 'authorization_code',
            'code' => $request->query->get('code'),
            'client_secret' => $settingsManager->get('stripe_secret_key'),
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
        } else {
            $this->addFlash(
                'notice',
                $this->get('translator')->trans('form.local_business.stripe_account.success')
            );

            Stripe\Stripe::setApiKey($settingsManager->get('stripe_secret_key'));

            $account = Stripe\Account::retrieve($res['stripe_user_id']);

            $stripeAccount = new StripeAccount();
            $stripeAccount
                ->setType($account->type)
                ->setDisplayName($account->display_name)
                ->setPayoutsEnabled($account->payouts_enabled)
                ->setStripeUserId($res['stripe_user_id'])
                ->setRefreshToken($res['refresh_token'])
                ->setLivemode($res['livemode'])
                ;

            $this->getUser()->addStripeAccount($stripeAccount);
            $this->get('fos_user.user_manager')->updateUser($this->getUser());

            if ($request->query->has('state')) {

                $restaurantId = $request->query->get('state');

                $restaurant = $this->getDoctrine()->getRepository(Restaurant::class)->find($restaurantId);
                $restaurant->addStripeAccount($stripeAccount);

                $this->getDoctrine()->getManagerForClass(Restaurant::class)->flush();

                return $this->redirectToRoute('profile_restaurant', ['id' => $restaurantId]);
            }
        }

        return $this->redirectToRoute('profile_restaurants');
    }
}
