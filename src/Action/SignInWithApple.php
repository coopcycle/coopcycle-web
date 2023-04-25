<?php

namespace AppBundle\Action;

use AppBundle\JWT\Validation\Constraint\PermittedForOneOf;
use Azimo\Apple\Api;
use Azimo\Apple\Auth;
use Azimo\Apple\Auth\Exception\ValidationFailedException;
use GuzzleHttp\Client;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\PermittedFor;
use Lcobucci\JWT\Validation\Validator;
use Nucleos\UserBundle\Model\UserManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationSuccessResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SignInWithApple
{
    private $userManager;
    private $jwtManager;
    private $dispatcher;

    public function __construct(
        UserManagerInterface $userManager,
        JWTTokenManagerInterface $jwtManager,
        EventDispatcherInterface $dispatcher)
    {
        $this->userManager = $userManager;
        $this->jwtManager = $jwtManager;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @Route(
     *     path="/sign_in_with_apple/login",
     *     name="api_sign_in_with_apple_login",
     *     methods={"POST"}
     * )
     */
    public function signInWithAppleLoginAction(Request $request)
    {
        $data = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $data = json_decode($content, true);
        }

        $appleJwtFetchingService = new Auth\Service\AppleJwtFetchingService(
            new Auth\Jwt\JwtParser(new Parser(new JoseEncoder())),
            new Auth\Jwt\JwtVerifier(
                new Api\AppleApiClient(
                    new Client(
                        [
                            'base_uri'        => 'https://appleid.apple.com',
                            'timeout'         => 5,
                            'connect_timeout' => 5,
                        ]
                    ),
                    new Api\Factory\ResponseFactory()
                ),
                new Validator(),
                new Sha256()
            ),
            new Auth\Jwt\JwtValidator(
                new Validator(),
                [
                    new IssuedBy('https://appleid.apple.com'),
                    new PermittedForOneOf('org.coopcycle.CoopCycle', 'org.coopcycle.Naofood'),
                ]
            ),
            new Auth\Factory\AppleJwtStructFactory()
        );

        try {

            $payload = $appleJwtFetchingService->getJwtPayload($data['identityToken']);

            $email = $payload->getEmail();

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

        } catch (ValidationFailedException $e) {
            throw new AccessDeniedHttpException();
        }
    }
}
