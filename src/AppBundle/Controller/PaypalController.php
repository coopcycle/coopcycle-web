<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Restaurant;
use AppBundle\Entity\StripeAccount;
use AppBundle\Service\SettingsManager;
// use AppBundle\Service\StripeManager;
use FOS\UserBundle\Model\UserManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
// use Stripe;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use PayPal\Api\OpenIdTokeninfo;
use PayPal\Api\OpenIdUserinfo;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;

/**
 * @see https://developer.paypal.com/docs/connect-with-paypal/integrate/
 * https://developer.paypal.com/docs/connect-with-paypal/reference/#scope-attributes
 * https://developer.paypal.com/docs/integration/paypal-here/merchant-onboarding/#third-party
 * https://developer.paypal.com/docs/checkout/integration-features/custom-payee/
 * https://developer.paypal.com/docs/checkout/integrate/
 * https://developer.paypal.com/docs/checkout/reference/server-integration/set-up-transaction/
 */
class PaypalController extends AbstractController
{
    /**
     * @Route("/paypal/oauth/callback", name="paypal_oauth_callback")
     */
    public function connectStandardAccountAction(
        Request $request,
        JWTEncoderInterface $jwtEncoder,
        SettingsManager $settingsManager,
        UserManagerInterface $userManager)
    {
        // curl -X POST https://api.sandbox.paypal.com/v1/oauth2/token \
        // -H 'Authorization: Basic {Your Base64-encoded ClientID:Secret}=' \
        // -d 'grant_type=authorization_code&code={authorization_code}'

        $paypalClientId = $settingsManager->get('paypal_client_id');
        $paypalClientSecret = $settingsManager->get('paypal_client_secret');

        $basicAuth = base64_encode(sprintf('%s:%s', $paypalClientId, $paypalClientSecret));

        $headers = array(
            sprintf('Authorization: Basic %s', $basicAuth),
        );

        $params = array(
            'grant_type' => 'authorization_code',
            'code' => $request->query->get('code'),
        );

        $req = curl_init('https://api.sandbox.paypal.com/v1/oauth2/token');
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req, CURLOPT_POST, true);
        curl_setopt($req, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($req, CURLOPT_HTTPHEADER, $headers);

        // {
        //     "token_type": "Bearer",
        //     "expires_in": "28800",
        //     "refresh_token": {refresh_token},
        //     "access_token": {access_token}
        // }

        $respCode = curl_getinfo($req, CURLINFO_HTTP_CODE);
        $res = json_decode(curl_exec($req), true);
        curl_close($req);

        try {

            $apiContext = new ApiContext(
                new OAuthTokenCredential(
                    $paypalClientId,
                    $paypalClientSecret
                )
            );

            $tokenInfo = new OpenIdTokeninfo();
            $tokenInfo = $tokenInfo->createFromRefreshToken(array('refresh_token' => $res['refresh_token']), $apiContext);

            $params = array('access_token' => $tokenInfo->getAccessToken());
            $userInfo = OpenIdUserinfo::getUserinfo($params, $apiContext);

            // $payerId = $userInfo->getPayerId();

        } catch (\Exception $ex) {
            // // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
            // ResultPrinter::printError("User Information", "User Info", null, $params, $ex);
            // exit(1);
        }

        exit;
    }
}
