<?php

namespace AppBundle\Action;

use ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException;
use AppBundle\Entity\User;
use AppBundle\Form\ApiRegistrationType;
use Nucleos\ProfileBundle\Mailer\MailerInterface;
use Nucleos\UserBundle\Model\UserManagerInterface;
use Nucleos\UserBundle\Util\TokenGeneratorInterface;
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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use Nucleos\ProfileBundle\Event\GetResponseRegistrationEvent;
use Nucleos\ProfileBundle\Event\UserFormEvent;
use Nucleos\ProfileBundle\Form\Model\Registration;
use Nucleos\ProfileBundle\Form\Type\RegistrationFormType;
use Nucleos\ProfileBundle\NucleosProfileEvents;
use Nucleos\UserBundle\Event\FilterUserResponseEvent;
use Nucleos\UserBundle\Event\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

class Register
{
    private $userManager;
    private $jwtManager;
    private $dispatcher;
    private $formFactory;
    private $tokenGenerator;
    private $mailer;
    private $confirmationEnabled;
    /**
     * @var Environment
     */
    private $twig;

    public function __construct(
        UserManagerInterface $userManager,
        JWTTokenManagerInterface $jwtManager,
        EventDispatcherInterface $dispatcher,
        FormFactoryInterface $formFactory,
        TokenGeneratorInterface $tokenGenerator,
        MailerInterface $mailer,
        ValidatorInterface $validator,
        bool $confirmationEnabled,
        LoggerInterface $registerLogger,
        Environment $twig,
    )
    {
        $this->userManager = $userManager;
        $this->jwtManager = $jwtManager;
        $this->dispatcher = $dispatcher;
        $this->formFactory = $formFactory;
        $this->tokenGenerator = $tokenGenerator;
        $this->mailer = $mailer;
        $this->validator = $validator;
        $this->confirmationEnabled = $confirmationEnabled;
        $this->registerLogger = $registerLogger;
        $this->twig            = $twig;
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
        // create a log channel
        $log = new Logger('nameregisterAction');
        $log->pushHandler(new StreamHandler('php://stdout', Logger::WARNING)); // <<< uses a stream

        // add records to the log
        $log->warning('Foo registerAction');
        $log->error('Bar registerAction');
        $email = $request->request->get('_email');
        $username = $request->request->get('_username');
        $password = $request->request->get('_password');
        $password_confirmation = $request->request->get('_password_confirmation');
        $telephone = $request->request->get('_telephone');
        $givenName = $request->request->get('_givenName');
        $familyName = $request->request->get('_familyName');
        $fullName = $request->request->get('_fullName');
        $this->registerLogger->info('Request data : email={email}; username={username}; password={password}; password_confirmation={password_confirmation}; telephone={telephone}; givenName={givenName}; familyName={familyName}; fullName={fullName};', [
            'email' => $email,
            'username' => $username,
            'password' => $password,
            'password_confirmation' => $password_confirmation,
            'telephone' => $telephone,
            'givenName' => $givenName,
            'familyName' => $familyName,
            'fullName' => $fullName
        ]);
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

        $form = $this->formFactory->create(ApiRegistrationType::class);
        $form->submit($data);

        if (!$form->isValid()) {

            $violations = new ConstraintViolationList();
            foreach ($form->getErrors(true) as $error) {
                $cause = $error->getCause();
                if ($cause instanceof ConstraintViolationInterface) {
                    $violations->add($cause);
                }
            }
            return new RedirectResponse('/register');
            //throw new ValidationException($violations);
        }

        $registration = $form->getData();

        $user = $registration->toUser($this->userManager);

        $violations = $this->validator->validate($user);
        $this->registerLogger->info('violations data : violations={violations};', [
            'violations' => $violations
        ]);
        if (count($violations) > 0) {
            return new RedirectResponse('/register');
            //throw new ValidationException($violations);
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

        return new Response($this->twig->render('@NucleosProfile/Registration/register.html.twig', [
            'form' => $form->createView(),
        ]));
        //return $response;
    }
}
