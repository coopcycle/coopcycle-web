<?php

namespace AppBundle\Controller;

use AppBundle\Edenred\Authentication;
use AppBundle\Entity\Sylius\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

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

        if (!$subject instanceof Customer) {
            throw new BadRequestHttpException(sprintf('The "sub" claim should be an instance of "%s"', Customer::class));
        }

        if ($request->query->has('error')) {

            $this->addFlash('error', 'There was an error while trying to connect your Edenred account.');

            return $this->redirectToRoute('fos_user_profile_show');
        }

        $data = $this->authentication->authorizationCode($request->query->get('code'));

        if (false === $data) {
            $this->addFlash('error', 'There was an error while trying to connect your Edenred account.');

            // TODO Redirect depending on context
            return $this->redirectToRoute('fos_user_profile_show');
        }

        $subject->setEdenredAccessToken($data['access_token']);
        $subject->setEdenredRefreshToken($data['refresh_token']);

        $entityManager->flush();

        $this->addFlash('notice', $translator->trans('edenred.oauth_connect.success'));

        // TODO Redirect depending on context
        return $this->redirectToRoute('fos_user_profile_show');
    }
}
