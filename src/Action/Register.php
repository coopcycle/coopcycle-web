<?php

namespace AppBundle\Action;

use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use AppBundle\Entity\User;
use Nucleos\ProfileBundle\Form\Type\RegistrationFormType;
use Nucleos\ProfileBundle\Mailer\RegistrationMailer;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Nucleos\UserBundle\Util\TokenGenerator as TokenGeneratorInterface;
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
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Register
{
    private $userManager;
    private $jwtManager;
    private $dispatcher;
    private $formFactory;
    private $tokenGenerator;
    private $mailer;
    private $confirmationEnabled;

    public function __construct(
        UserManagerInterface $userManager,
        JWTTokenManagerInterface $jwtManager,
        EventDispatcherInterface $dispatcher,
        FormFactoryInterface $formFactory,
        TokenGeneratorInterface $tokenGenerator,
        RegistrationMailer $mailer,
        ValidatorInterface $validator,
        bool $confirmationEnabled)
    {
        $this->userManager = $userManager;
        $this->jwtManager = $jwtManager;
        $this->dispatcher = $dispatcher;
        $this->formFactory = $formFactory;
        $this->tokenGenerator = $tokenGenerator;
        $this->mailer = $mailer;
        $this->validator = $validator;
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
                'first' => $password,
                'second' => $password
            ],
            'givenName' => $givenName,
            'familyName' => $familyName,
            'telephone' => $telephone,
            'fullName' => $fullName,
        ];

        $user = $this->userManager->createUser();

        $form = $this->formFactory->create(RegistrationFormType::class, $user, ['usage_context' => 'api']);

        $form->submit($data);

        $user = $form->getData();

        $violations = $this->validator->validate($user, null, ['Registration', 'Default']);

        if (count($violations) > 0) {
            throw new ValidationException($violations);
        }

        $user->setEnabled($this->confirmationEnabled ? false : true);

        $this->userManager->updateUser($user);

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
