<?php

namespace AppBundle\Action;

use AppBundle\Entity\Order;
use AppBundle\Entity\ApiUser;
use Doctrine\Common\Persistence\ManagerRegistry;
use FOS\UserBundle\Util\UserManipulator;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationSuccessResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Register
{
    private $userManipulator;
    private $jwtManager;
    private $dispatcher;

    public function __construct(UserManipulator $userManipulator, JWTManager $jwtManager, EventDispatcherInterface $dispatcher)
    {
        $this->userManipulator = $userManipulator;
        $this->jwtManager = $jwtManager;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @Route(
     *     path="/register",
     *     name="api_register"
     * )
     * @Method("POST")
     */
    public function registerAction(Request $request)
    {
        $email = $request->request->get('_email');
        $username = $request->request->get('_username');
        $password = $request->request->get('_password');

        try {
            $user = $this->userManipulator->create($username, $password, $email, true, false);
            $jwt = $this->jwtManager->create($user);
        } catch (\Exception $e) {
            // TODO Send JSON-LD response
            throw new BadRequestHttpException($e);
        }

        // See Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler
        $response = new JWTAuthenticationSuccessResponse($jwt);
        $event    = new AuthenticationSuccessEvent(['token' => $jwt], $user, $response);

        $this->dispatcher->dispatch(Events::AUTHENTICATION_SUCCESS, $event);
        $response->setData($event->getData());

        return $response;
    }
}
