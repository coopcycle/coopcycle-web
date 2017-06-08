<?php

namespace AppBundle\Controller;

use AppBundle\Utils\Cart;
use AppBundle\Entity\Restaurant;
use AppBundle\Entity\Order;
use AppBundle\Entity\StripeParams;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class StripeController extends Controller
{
    /**
     * @Route("/stripe/connect", name="stripe_connect")
     */
    public function connectAction(Request $request)
    {
       // curl https://connect.stripe.com/oauth/token \
       // -d client_secret=XXX \
       // -d code=AUTHORIZATION_CODE \
       // -d grant_type=authorization_code

        $params = array(
            'grant_type' => 'authorization_code',
            'code' => $request->query->get('code'),
            'client_secret' => $this->getParameter('stripe_secret_key'),
        );

        $req = curl_init('https://connect.stripe.com/oauth/token');
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req, CURLOPT_POST, true);
        curl_setopt($req, CURLOPT_POSTFIELDS, http_build_query($params));

        // TODO: Additional error handling
        $respCode = curl_getinfo($req, CURLINFO_HTTP_CODE);
        $resp = json_decode(curl_exec($req), true);
        curl_close($req);

        if (isset($resp['error']) && !empty($resp['error'])) {
            // TODO error handling
            throw new \Exception($resp['error_description']);
        }

        $stripeParams = new StripeParams();
        $stripeParams
            ->setUserId($resp['stripe_user_id'])
            // ->setPublishableKey($resp['stripe_publishable_key'])
            // ->setAccessToken($resp['access_token'])
            // ->setRefreshToken($resp['refresh_token'])
            ;

        $this->getUser()->setStripeParams($stripeParams);
        foreach ($this->getUser()->getRestaurants() as $restaurant) {
            $restaurant->setStripeParams($stripeParams);
        }

        $em = $this->getDoctrine()->getManagerForClass(get_class($this->getUser()));
        $em->flush();

        return $this->redirectToRoute('profile_payment');
    }

}
