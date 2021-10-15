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
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FacebookLogin
{
    private $userManager;
    private $jwtManager;
    private $dispatcher;
    private $facebookClient;

    public function __construct(
        UserManagerInterface $userManager,
        JWTTokenManagerInterface $jwtManager,
        EventDispatcherInterface $dispatcher,
        HttpClientInterface $facebookClient)
    {
        $this->userManager = $userManager;
        $this->jwtManager = $jwtManager;
        $this->dispatcher = $dispatcher;
        $this->facebookClient = $facebookClient;
    }

    /**
     * @Route(
     *     path="/facebook/login",
     *     name="api_facebook_login",
     *     methods={"POST"}
     * )
     */
    public function facebookLoginAction(Request $request)
    {
        $data = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $data = json_decode($content, true);
        }

        $content = [];

        try {

            // https://graph.facebook.com/me?fields=email,name,first_name,last_name&access_token=XXX
            $facebookResponse = $this->facebookClient->request('GET', 'me', [
                'query' => [
                    'fields' => 'email,name,first_name,last_name',
                    'access_token' => $data['accessToken'],
                ],
            ]);

            // Need to invoke a method on the Response,
            // to actually throw the Exception here
            // https://github.com/symfony/symfony/issues/34281
            $statusCode = $facebookResponse->getStatusCode();

            $fbContent = $facebookResponse->toArray();

            $email = $fbContent['email'];

            $user = $this->userManager->findUserByEmail($email);

            if (null === $user) {
                throw new AccessDeniedException();
            }

            $jwt = $this->jwtManager->create($user);

            // @see Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler
            $response = new JWTAuthenticationSuccessResponse($jwt);
            $event    = new AuthenticationSuccessEvent(['token' => $jwt], $user, $response);

            $this->dispatcher->dispatch($event, Events::AUTHENTICATION_SUCCESS);
            $response->setData($event->getData());

            return $response;

        } catch (HttpExceptionInterface | TransportExceptionInterface $e) {
            throw new AccessDeniedException();
        }
    }
}
