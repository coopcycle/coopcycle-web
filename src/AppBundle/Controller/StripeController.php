<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\StripeAccount;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Stripe;
use Symfony\Component\HttpFoundation\Request;

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
                ->setRefreshToken($res['refresh_token']);

            $this->getUser()->addStripeAccount($stripeAccount);
            $this->get('fos_user.user_manager')->updateUser($this->getUser());

            if ($request->query->has('state')) {
                $restaurantId = $request->query->get('state');
                $em = $this->getDoctrine();
                $restaurant = $em->getRepository(Restaurant::class)->find($restaurantId);
                $restaurant->setStripeAccount($stripeAccount);
                $em->getManagerForClass(Restaurant::class)->persist($restaurant);
                $em->getManagerForClass(Restaurant::class)->flush();

                return $this->redirectToRoute('profile_restaurant', ['id' => $restaurantId]);
            }
        }

        return $this->redirectToRoute('profile_restaurants');
    }
}
