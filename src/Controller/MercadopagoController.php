<?php

namespace AppBundle\Controller;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\ItemNotFoundException;
use AppBundle\Entity\MercadopagoAccount;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityManagerInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @see https://www.mercadopago.com.br/developers/es/guides/marketplace/web-checkout/create-marketplace/
 */
class MercadopagoController extends AbstractController
{
    /**
     * @Route("/mercadopago/oauth/callback", name="mercadopago_oauth_callback")
     */
    public function oAuthCallbackAction(
        Request $request,
        JWTEncoderInterface $jwtEncoder,
        SettingsManager $settingsManager,
        UserManagerInterface $userManager,
        UrlGeneratorInterface $urlGenerator,
        IriConverterInterface $iriConverter,
        TranslatorInterface $translator,
        EntityManagerInterface $objectManager)
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

        if (!isset($payload['iss']) || !isset($payload['sub']) || !isset($payload['mplm'])) {
            throw $this->createAccessDeniedException();
        }

        try {
            $restaurant = $iriConverter->getItemFromIri($payload['sub']);
        } catch (ItemNotFoundException $e) {
            throw $this->createAccessDeniedException();
        }

        $redirect = $payload['iss'];
        $livemode = filter_var($payload['mplm'], FILTER_VALIDATE_BOOLEAN);

        $accessToken = $livemode ?
            $settingsManager->get('mercadopago_live_access_token') : $settingsManager->get('mercadopago_test_access_token');

        $redirectUri = $this->generateUrl(
            'mercadopago_oauth_callback',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // curl -X POST \
        // -H 'accept: application/json' \
        // -H 'content-type: application/x-www-form-urlencoded' \
        // 'https://api.mercadopago.com/oauth/token' \
        // -d 'client_secret=ACCESS_TOKEN' \
        // -d 'grant_type=authorization_code' \
        // -d 'code=AUTHORIZATION_CODE' \
        // -d 'redirect_uri=REDIRECT_URI'

        $params = array(
            'grant_type' => 'authorization_code',
            'code' => $request->query->get('code'),
            'client_secret' => $accessToken,
            'redirect_uri' => $redirectUri,
        );

        $req = curl_init('https://api.mercadopago.com/oauth/token');
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req, CURLOPT_POST, true);
        curl_setopt($req, CURLOPT_POSTFIELDS, http_build_query($params));

        // TODO: Additional error handling
        $respCode = curl_getinfo($req, CURLINFO_HTTP_CODE);
        $res = json_decode(curl_exec($req), true);
        curl_close($req);

        // Array (
        //     [access_token] => ****
        //     [token_type] => bearer
        //     [expires_in] => 15552000
        //     [scope] => offline_access read write
        //     [user_id] => ****
        //     [refresh_token] => ****
        //     [public_key] => ****
        //     [live_mode] =>
        // )

        if (isset($res['error']) && !empty($res['error'])) {
            $this->addFlash(
                'error',
                $res['error_description']
            );

            return $this->redirectToRoute('homepage');
        }

        $account = new MercadopagoAccount();
        $account
            ->setUserId($res['user_id'])
            ->setAccessToken($res['access_token'])
            ->setRefreshToken($res['refresh_token'])
            ->setLivemode($res['live_mode'])
            ;

        $this->getUser()->addMercadopagoAccount($account);

        $restaurant->addMercadopagoAccount($account);

        $objectManager->flush();

        $this->addFlash(
            'notice',
            $translator->trans('form.local_business.mercadopago_account.success')
        );

        return $this->redirect($redirect);
    }
}
