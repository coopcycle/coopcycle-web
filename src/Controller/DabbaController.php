<?php

namespace AppBundle\Controller;

use ApiPlatform\Core\Api\IriConverterInterface;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Dabba\Client as DabbaClient;
use AppBundle\Dabba\GuestCheckoutAwareAdapter as DabbaAdapter;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Psr\Log\LoggerInterface;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class DabbaController extends AbstractController
{
    public function __construct(
        DabbaClient $dabbaClient,
        LoggerInterface $logger)
    {
        $this->dabbaClient = $dabbaClient;
        $this->logger = $logger;
    }

    /**
     * @Route("/dabba/oauth/callback", name="dabba_oauth_callback")
     */
    public function callbackAction(
        Request $request,
        JWTEncoderInterface $jwtEncoder,
        IriConverterInterface $iriConverter,
        EntityManagerInterface $entityManager,
        RequestStack $requestStack,
        TranslatorInterface $translator,
        CartContextInterface $cartContext,
        OrderProcessorInterface $orderProcessor)
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

        if (!isset($payload['sub'])) {
            throw $this->createAccessDeniedException();
        }

        if (!$request->query->has('code') && !$request->query->has('error')) {
            throw new BadRequestHttpException('Request has no "code" or "error" parameter.');
        }

        if ($request->query->has('error')) {
            $this->addFlash('error', 'There was an error while trying to connect your Dabba account.');

            return $this->redirectToRoute('order');
        }

        $subject = $iriConverter->getItemFromIri($payload['sub']);

        if (!$subject instanceof Order) {
            throw new BadRequestHttpException(sprintf('Subject should be an instance of "%s"', Order::class));
        }

        $data = $this->dabbaClient->authorizationCode($request->query->get('code'));

        if (false === $data) {
            $this->addFlash('error', 'There was an error while trying to connect your Dabba account.');

            return $this->redirectToRoute('order');
        }

        $session = $requestStack->getSession();

        $adapter = new DabbaAdapter($subject, $session);
        $adapter->setDabbaAccessToken($data['access_token']);
        $adapter->setDabbaRefreshToken($data['refresh_token']);

        // Enable reusable packaging now
        $subject->setReusablePackagingEnabled(true);
        $orderProcessor->process($subject);

        $entityManager->flush();

        $this->addFlash('notice', $translator->trans('dabba.oauth_connect.success'));

        return $this->redirectToRoute('order');
    }
}
