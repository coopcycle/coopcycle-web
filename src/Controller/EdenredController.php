<?php

namespace AppBundle\Controller;

use AppBundle\Edenred\Authentication;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\Sylius\Order;
use AppBundle\Sylius\Customer\CustomerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class EdenredController extends AbstractController
{
    public function __construct(
        Authentication $authentication,
        LoggerInterface $logger)
    {
        $this->authentication = $authentication;
        $this->logger = $logger;
    }

    /**
     * @Route("/edenred/oauth/callback", name="edenred_oauth_callback")
     */
    public function oauthCallbackAction(Request $request,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator)
    {
        if (!$request->query->has('code') && !$request->query->has('error')) {
            throw new BadRequestHttpException('Request has no "code" or "error" parameter.');
        }

        if (!$request->query->has('state')) {
            throw $this->createAccessDeniedException();
        }

        $state = $request->query->get('state');

        try {
            $payload = $this->authentication->decodeState($state);
        } catch (JWTDecodeFailureException $e) {
            throw $this->createAccessDeniedException();
        }

        if (!isset($payload['sub'])) {
            throw $this->createAccessDeniedException();
        }

        $subject = $this->authentication->getSubject($payload);

        if (!$subject instanceof Customer && !$subject instanceof Order) {
            throw new BadRequestHttpException(sprintf('The "sub" claim should be an instance of "%s" or "%s',
                Customer::class, Order::class));
        }

        if ($request->query->has('error')) {

            $this->addFlash('error', 'There was an error while trying to connect your Edenred account.');

            return $this->redirectToRoute('nucleos_profile_profile_show');
        }

        $data = $this->authentication->authorizationCode($request->query->get('code'));

        if (false === $data) {
            $this->addFlash('error', 'There was an error while trying to connect your Edenred account.');

            // TODO Redirect depending on context
            return $this->redirectToRoute('nucleos_profile_profile_show');
        }

        $customer = $subject instanceof Order ? $subject->getCustomer() : $subject;

        Assert::isInstanceOf($customer, CustomerInterface::class);

        $customer->setEdenredAccessToken($data['access_token']);
        $customer->setEdenredRefreshToken($data['refresh_token']);

        $entityManager->flush();

        $this->addFlash('notice', $translator->trans('edenred.oauth_connect.success'));

        return $this->redirectToRoute(
            $subject instanceof Order ? 'order_payment' : 'nucleos_profile_profile_show'
        );
    }
}
