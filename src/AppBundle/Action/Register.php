<?php

namespace AppBundle\Action;

use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use AppBundle\Entity\ApiUser;
use AppBundle\Form\ApiRegistrationType;
use FOS\UserBundle\Model\UserManagerInterface;
use FOS\UserBundle\Util\UserManipulator;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationSuccessResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;

class Register
{
    private $userManipulator;
    private $userManager;
    private $jwtManager;
    private $dispatcher;
    private $formFactory;

    public function __construct(
        UserManipulator $userManipulator,
        UserManagerInterface $userManager,
        JWTTokenManagerInterface $jwtManager,
        EventDispatcherInterface $dispatcher,
        FormFactoryInterface $formFactory)
    {
        $this->userManipulator = $userManipulator;
        $this->userManager = $userManager;
        $this->jwtManager = $jwtManager;
        $this->dispatcher = $dispatcher;
        $this->formFactory = $formFactory;
    }

    /**
     * @Route(
     *     path="/register",
     *     name="api_register",
     *     methods={"POST"}
     * )
     */
    public function registerAction(Request $request)
    {
        $email = $request->request->get('_email');
        $username = $request->request->get('_username');
        $password = $request->request->get('_password');
        $telephone = $request->request->get('_telephone');
        $givenName = $request->request->get('_givenName');
        $familyName = $request->request->get('_familyName');

        $data = [
            'email' => $email,
            'username' => $username,
            'plainPassword' => [
                'password' => $password,
                'password_confirmation' => $password
            ],
            'givenName' => $givenName,
            'familyName' => $familyName,
            'telephone' => $telephone
        ];

        $user = new ApiUser();

        $form = $this->formFactory->create(ApiRegistrationType::class, $user);
        $form->submit($data);

        if (!$form->isValid()) {

            $violations = new ConstraintViolationList();
            foreach ($form->getErrors(true) as $error) {
                $cause = $error->getCause();
                if ($cause instanceof ConstraintViolationInterface) {
                    $violations->add($cause);
                }
            }

            throw new ValidationException($violations);
        }

        try {

            // TODO Customize FOSUserBundle manipulator to pass all fields at once
            $user = $this->userManipulator->create($username, $password, $email, true, false);

            $user->setTelephone($form->get('telephone')->getData());
            $user->setGivenName($form->get('givenName')->getData());
            $user->setFamilyName($form->get('familyName')->getData());

            $this->userManager->updateUser($user);

        } catch (\Exception $e) {
            // TODO Send JSON-LD response
            throw new BadRequestHttpException($e);
        }

        $jwt = $this->jwtManager->create($user);

        // See Lexik\Bundle\JWTAuthenticationBundle\Security\Http\Authentication\AuthenticationSuccessHandler
        $response = new JWTAuthenticationSuccessResponse($jwt);
        $event    = new AuthenticationSuccessEvent(['token' => $jwt], $user, $response);

        $this->dispatcher->dispatch(Events::AUTHENTICATION_SUCCESS, $event);
        $response->setData($event->getData());

        return $response;
    }
}
