<?php

namespace AppBundle\Action;

use Nucleos\UserBundle\Model\UserManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationSuccessResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class GoogleSignIn
{
    private $userManager;
    private $jwtManager;
    private $dispatcher;
    private $appId;

    public function __construct(
        UserManagerInterface $userManager,
        JWTTokenManagerInterface $jwtManager,
        EventDispatcherInterface $dispatcher,
        string $appId)
    {
        $this->userManager = $userManager;
        $this->jwtManager = $jwtManager;
        $this->dispatcher = $dispatcher;
        $this->appId = $appId;
    }

    /**
     * @Route(
     *     path="/google_sign_in/login",
     *     name="api_google_sign_in_login",
     *     methods={"POST"}
     * )
     */
    public function googleSignInAction(Request $request)
    {
        $data = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $data = json_decode($content, true);
        }

        $client = new \Google_Client(['client_id' => $this->appId]);
        $payload = $client->verifyIdToken($data['idToken']);

        if (!$payload) {
          throw new AccessDeniedHttpException();
        }

        $email = $payload['email'];

        $user = $this->userManager->findUserByEmail($email);

        if (null === $user) {
            throw new AccessDeniedHttpException();
        }

        $jwt = $this->jwtManager->create($user);

        // @see Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler
        $response = new JWTAuthenticationSuccessResponse($jwt);
        $event    = new AuthenticationSuccessEvent(['token' => $jwt], $user, $response);

        $this->dispatcher->dispatch($event, Events::AUTHENTICATION_SUCCESS);
        $response->setData($event->getData());

        return $response;
    }
}
