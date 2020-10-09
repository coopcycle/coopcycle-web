<?php

namespace AppBundle\Action;

use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use AppBundle\Entity\User;
use AppBundle\Form\ApiRegistrationType;
use FOS\UserBundle\Mailer\MailerInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use FOS\UserBundle\Util\UserManipulator;
use FOS\UserBundle\Util\TokenGeneratorInterface;
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
    private $tokenGenerator;
    private $mailer;
    private $confirmationEnabled;

    public function __construct(
        UserManipulator $userManipulator,
        UserManagerInterface $userManager,
        JWTTokenManagerInterface $jwtManager,
        EventDispatcherInterface $dispatcher,
        FormFactoryInterface $formFactory,
        TokenGeneratorInterface $tokenGenerator,
        MailerInterface $mailer,
        bool $confirmationEnabled)
    {
        $this->userManipulator = $userManipulator;
        $this->userManager = $userManager;
        $this->jwtManager = $jwtManager;
        $this->dispatcher = $dispatcher;
        $this->formFactory = $formFactory;
        $this->tokenGenerator = $tokenGenerator;
        $this->mailer = $mailer;
        $this->confirmationEnabled = $confirmationEnabled;
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
        $fullName = $request->request->get('_fullName');

        $data = [
            'email' => $email,
            'username' => $username,
            'plainPassword' => [
                'password' => $password,
                'password_confirmation' => $password
            ],
            'givenName' => $givenName,
            'familyName' => $familyName,
            'telephone' => $telephone,
            'fullName' => $fullName,
        ];

        $user = $this->userManager->createUser();

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

            $enabled = $this->confirmationEnabled ? false : true;

            $user = $this->userManipulator->create($username, $password, $email, $enabled, false);

            $user->setTelephone($form->get('telephone')->getData());

            if (!empty($fullName)) {
                $user->getCustomer()->setFullName($fullName);
            } else {
                $user->getCustomer()->setFirstName($form->get('givenName')->getData());
                $user->getCustomer()->setLastName($form->get('familyName')->getData());
            }

            $this->userManager->updateUser($user);

        } catch (\Exception $e) {
            // FIXME If a "real" error occurs, it is hidden
            // TODO Send JSON-LD response
            throw new BadRequestHttpException($e);
        }

        // @see FOS\UserBundle\EventListener\EmailConfirmationListener
        if ($this->confirmationEnabled) {
            if (null === $user->getConfirmationToken()) {
                $user->setConfirmationToken($this->tokenGenerator->generateToken());
            }
            $this->mailer->sendConfirmationEmailMessage($user);
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
