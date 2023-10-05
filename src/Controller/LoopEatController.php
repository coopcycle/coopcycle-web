<?php

namespace AppBundle\Controller;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\LoopEat\Client as LoopEatClient;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoopEatController extends AbstractController
{
    public function __construct(
        string $loopeatBaseUrl,
        string $loopeatClientId,
        string $loopeatClientSecret,
        string $loopeatOAuthFlow,
        LoopEatClient $loopeatClient,
        LoggerInterface $logger)
    {
        $this->loopeatBaseUrl = $loopeatBaseUrl;
        $this->loopeatClientId = $loopeatClientId;
        $this->loopeatClientSecret = $loopeatClientSecret;
        $this->loopeatOAuthFlow = $loopeatOAuthFlow;
        $this->loopeatClient = $loopeatClient;
        $this->logger = $logger;
    }

    private function authorizationCode($code)
    {
        $params = array(
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->loopeatClientId,
            'client_secret' => $this->loopeatClientSecret,
            'redirect_uri' => $this->generateUrl('loopeat_oauth_callback', [], UrlGeneratorInterface::ABSOLUTE_URL),
        );

        $ch = curl_init(sprintf('%s/oauth/token', $this->loopeatBaseUrl));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));

        $res = curl_exec($ch);

        $httpCode = !curl_errno($ch) ? curl_getinfo($ch, CURLINFO_HTTP_CODE) : null;

        if ($httpCode !== 200) {

            $data = json_decode($res, true);

            $this->logger->error(sprintf('There was an "%s" error when trying to fetch an access token from LoopEat: "%s"',
                $data['error'], $data['error_description']));

            curl_close($ch);

            return false;
        }

        curl_close($ch);

        return json_decode($res, true);
    }

    private function getFailureRedirect(array $payload)
    {
        if (isset($payload[LoopEatClient::JWT_CLAIM_FAILURE_REDIRECT])) {
            return $payload[LoopEatClient::JWT_CLAIM_FAILURE_REDIRECT];
        }

        return $payload['iss'];
    }

    private function getSuccessRedirect(array $payload)
    {
        if (isset($payload[LoopEatClient::JWT_CLAIM_SUCCESS_REDIRECT])) {
            return $payload[LoopEatClient::JWT_CLAIM_SUCCESS_REDIRECT];
        }

        return $payload['iss'];
    }

    /**
     * @Route("/impec/oauth/callback", name="loopeat_oauth_callback")
     */
    public function connectStandardAccountAction(
        Request $request,
        JWTEncoderInterface $jwtEncoder,
        IriConverterInterface $iriConverter,
        EntityManagerInterface $objectManager,
        TranslatorInterface $translator)
    {
        if (!$request->query->has('state')) {
            $this->logger->error('No "state" parameter found in request');
            throw $this->createAccessDeniedException();
        }

        $state = $request->query->get('state');

        try {
            $payload = $jwtEncoder->decode($state);
        } catch (JWTDecodeFailureException $e) {
            $this->logger->error('Could not decode JWT');
            throw $this->createAccessDeniedException();
        }

        if (!isset($payload['sub'])) {
            throw $this->createAccessDeniedException();
        }

        $subject = $iriConverter->getItemFromIri($payload['sub']);

        if (!$subject instanceof LocalBusiness && !$subject instanceof Customer && !$subject instanceof Order) {
            throw new BadRequestHttpException(sprintf('Subject should be an instance of "%s" or "%s" or "%s"',
                LocalBusiness::class, Customer::class, Order::class));
        }

        if (!$request->query->has('code') && !$request->query->has('error')) {
            throw new BadRequestHttpException('Request has no "code" or "error" parameter.');
        }

        if ($request->query->has('error')) {

            return $this->redirect($this->getFailureRedirect($payload));
        }

        $data = $this->authorizationCode($request->query->get('code'));

        if (false === $data) {
            $this->addFlash('error', 'There was an error while trying to connect your LoopEat account.');

            return $this->redirect($this->getFailureRedirect($payload));
        }

        // This happens for guest checkout
        if ($subject instanceof Order && null !== $subject->getCustomer()) {
            $subject = $subject->getCustomer();
        }

        // If the customer is not defined yet,
        // we store the credentials in order
        // they will be attached to customer later
        if ($subject instanceof Order) {
            $this->logger->info(sprintf('Attaching LoopEat credentials to order #%d', $subject->getId()));
        } elseif ($subject instanceof LocalBusiness) {
            $this->logger->info(sprintf('Attaching LoopEat credentials to restaurant "%s"', $subject->getName()));
        } else {
            $this->logger->info(sprintf('Attaching LoopEat credentials to customer "%s"', $subject->getEmailCanonical()));
        }

        $subject->setLoopeatAccessToken($data['access_token']);
        $subject->setLoopeatRefreshToken($data['refresh_token']);

        $objectManager->flush();

        $initiative = $this->loopeatClient->initiative();

        $this->addFlash('notice', $translator->trans('loopeat.oauth_connect.success', [
            '%name%' => $initiative['name']
        ]));

        return $this->redirect($this->getSuccessRedirect($payload));
    }

    /**
     * @Route("/loopeat/success", name="loopeat_success")
     */
    public function successAction(Request $request,
        CartContextInterface $cartContext,
        OrderProcessorInterface $orderProcessor,
        EntityManagerInterface $objectManager)
    {
        if ('iframe' === $this->loopeatOAuthFlow) {
            return $this->render('loopeat/post_message.html.twig', ['loopeat_success' => true]);
        }

        $cart = $cartContext->getCart();

        if (null === $cart) {

            return $this->redirectToRoute('homepage');
        }

        $cart->setReusablePackagingEnabled(true);

        $orderProcessor->process($cart);

        $objectManager->flush();

        return $this->redirectToRoute('order');
    }

    /**
     * @Route("/loopeat/failure", name="loopeat_failure")
     */
    public function failureAction()
    {
        if ('iframe' === $this->loopeatOAuthFlow) {
            return $this->render('loopeat/post_message.html.twig', ['loopeat_success' => false]);
        }
        return $this->redirectToRoute('order');
    }
}
