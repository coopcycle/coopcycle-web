<?php

namespace AppBundle\Action;

use AppBundle\Entity\ApiUser;
use AppBundle\Form\ApiRegistrationType;
use FOS\UserBundle\Model\UserManagerInterface;
use FOS\UserBundle\Util\UserManipulator;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Lexik\Bundle\JWTAuthenticationBundle\Response\JWTAuthenticationSuccessResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Register
{
    private $userManipulator;
    private $jwtManager;
    private $dispatcher;

    public function __construct(
        UserManipulator $userManipulator,
        UserManagerInterface $userManager,
        JWTManager $jwtManager,
        EventDispatcherInterface $dispatcher,
        FormFactoryInterface $formFactory
    )
    {
        $this->userManipulator = $userManipulator;
        $this->jwtManager = $jwtManager;
        $this->dispatcher = $dispatcher;
        $this->formFactory = $formFactory;
        $this->userManager = $userManager;
    }

    public function getFormErrorsArray ($form) {
        $errors = [];

        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }

        foreach ($form->all() as $child ) {
            if (!$child->isValid()) {
                $errors[$child->getName()] = $this->getFormErrorsArray($child);
            }
        }

        return $errors;
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
            $errors = $this->getFormErrorsArray($form);
            return new JsonResponse($errors, 400);
        }

        try {
            // TODO Customize FOSUserBundle manipulator to pass all fields at once
            $user = $this->userManipulator->create($username, $password, $email, true, false);
            $jwt = $this->jwtManager->create($user);
            $user->setTelephone($form->get('telephone')->getData());
            $user->setGivenName($form->get('givenName')->getData());
            $user->setFamilyName($form->get('familyName')->getData());
            $this->userManager->updateUser($user);
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
