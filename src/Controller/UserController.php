<?php

namespace AppBundle\Controller;

use AppBundle\Entity\BusinessAccountInvitation;
use AppBundle\Entity\Invitation;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Store;
use AppBundle\Entity\Sylius\Customer;
use AppBundle\Entity\User;
use AppBundle\Form\BusinessAccountRegistration;
use AppBundle\Form\BusinessAccountRegistrationFlow;
use AppBundle\Sylius\Order\OrderFactory;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\ProfileBundle\NucleosProfileEvents;
use AppBundle\Form\Model\Registration;
use Nucleos\ProfileBundle\Mailer\RegistrationMailer;
use Nucleos\UserBundle\Event\FilterUserResponseEvent;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Nucleos\UserBundle\Util\Canonicalizer;
use Nucleos\ProfileBundle\Form\Type\RegistrationFormType;
use Laravolt\Avatar\Avatar;
use Shahonseven\ColorHash;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserController extends AbstractController
{
    private function createSuggestions(UserManagerInterface $userManager, SlugifyInterface $slugify, $email, $index = 0)
    {
        if (empty($email)) {
            return [];
        }

        if (false !== strpos($email, '@')) {
            $parts = explode('@', $email);
            $email = $parts[0];
        }

        // username must match /^[a-zA-Z0-9_]{3,15}$/
        $username = $slugify->slugify($email, ['separator' => '_']);
        $username = substr($username, 0, 15);

        if ($index > 0) {
            $username = sprintf('%s_%d', $username, $index);
        }

        $user = $userManager->findUserByUsername($username);
        if (null !== $user) {

            return $this->createSuggestions($userManager, $slugify, $email, ++$index);
        }

        return [
            $username,
        ];
    }

    /**
     * @Route("/register/suggest", name="register_suggest")
     */
    public function usernameExistsAction(Request $request, UserManagerInterface $userManager, SlugifyInterface $slugify)
    {
        if (!$request->query->has('username')) {
            throw new BadRequestHttpException('Missing "username" parameter');
        }

        if (!$request->query->has('email')) {
            throw new BadRequestHttpException('Missing "email" parameter');
        }

        $username = $request->query->get('username');
        $email = $request->query->get('email');

        if (empty($email)) {
            $email = $username;
        }

        $user = null;
        if (!empty($username)) {
            $user = $userManager->findUserByUsername($username);
        }

        $suggestions = $this->createSuggestions($userManager, $slugify, $email);

        if (null !== $user) {
            $data = ['exists' => true, 'suggestions' => $suggestions];
        } else {
            $data = ['exists' => false, 'suggestions' => $suggestions];
        }

        return new JsonResponse($data);
    }

    /**
     * @Route("/register/check-email-exists", name="register_check_email_exists")
     */
    public function emailExistsAction(Request $request, UserManagerInterface $userManager, TranslatorInterface $translator)
    {
        if (!$request->query->has('email')) {
            throw new BadRequestHttpException('Missing "email" parameter');
        }

        $email = $request->query->get('email');

        $user = null;
        if (!empty($email)) {
            $user = $userManager->findUserByEmail($email);
        }

        return new JsonResponse([
            'exists' => null !== $user,
            'errorMessage' => null !== $user ? $translator->trans('nucleos_user.email.already_used', [], 'validators') : ''
        ]);
    }

    /**
     * @Route("/users/{username}", name="user")
     */
    public function indexAction($username, UserManagerInterface $userManager)
    {
        $user = $userManager->findUserByUsername($username);

        return $this->render('user/index.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * @Route("/images/avatars/{username}.png", name="user_avatar")
     */
    public function avatarAction($username, Request $request)
    {
        $dir = $this->getParameter('avatar_dir');

        if (!file_exists($dir)) {
            mkdir($dir, 0755);
        }

        $colorHash = new ColorHash();

        $avatar = new Avatar([
            'uppercase' => true,
        ]);

        $avatar
            ->create($username)
            ->setBackground($colorHash->hex($username))
            ->save($dir . "${username}.png");

        list($type, $data) = explode(';', (string) $avatar->toBase64());
        list(, $data)      = explode(',', $data);
        $data = base64_decode($data);

        $response = new Response((string) $data);
        $response->headers->set('Content-Type', 'image/png');

        return $response;
    }

    /**
     * @Route("/register/resend-email", name="register_resend_email", methods={"POST"})
     */
    public function resendRegistrationEmailAction(Request $request, UserManagerInterface $userManager, RegistrationMailer $mailer, SessionInterface $session)
    {
        if ($request->request->has('email')) {
            $email = $request->request->get('email');
            $user = $userManager->findUserByEmail($email);
            if ($user !== null) {
                $mailer->sendConfirmationEmailMessage($user);
                $session->set('fos_user_send_confirmation_email/email', $email);
                return $this->redirectToRoute('nucleos_profile_registration_check_email');
            }
        }
        return $this->redirectToRoute('nucleos_user_security_login');
    }

    /**
     * @Route("/invitation/define-password/{code}", name="invitation_define_password")
     */
    public function confirmInvitationAction(Request $request, string $code,
        EntityManagerInterface $objectManager,
        UserManagerInterface $userManager,
        EventDispatcherInterface $eventDispatcher,
        Canonicalizer $canonicalizer,
        BusinessAccountRegistrationFlow $businessAccountRegistrationFlow,
        Security $security,
        TokenGeneratorInterface $tokenGenerator)
    {
        $repository = $this->getDoctrine()->getRepository(Invitation::class);

        if (null === $invitation = $repository->findOneByCode($code)) {
            throw $this->createNotFoundException();
        }

        $user = $userManager->findUserByEmail($invitation->getEmail());
        if (null === $user) {
            $user = $userManager->createUser();
            $user->setEmail($invitation->getEmail());
            $user->setEnabled(true);
        }

        $businessAccountInvitation = null;
        if ($this->getParameter('business_account_enabled')) {
            $businessAccountInvitation = $objectManager->getRepository(BusinessAccountInvitation::class)->findOneBy([
                'invitation' => $invitation,
            ]);
            if (null !== $businessAccountInvitation && $businessAccountInvitation->isInvitationForManager()) {
                return $this->loadBusinessAccountRegistrationFlow($request, $businessAccountRegistrationFlow, $user,
                    $businessAccountInvitation, $objectManager, $userManager, $eventDispatcher, $canonicalizer, $tokenGenerator);
            } else {
                $loggedInUser = $security->getUser();

                if ($loggedInUser) {
                    return $this->render('profile/associate_loggedin_user_to_business_account.html.twig', [
                        'show_left_menu' => false,
                        'businessAccountInvitation' => $businessAccountInvitation
                    ]);
                }

                // Reset object for a new user
                $user = $userManager->createUser();
                $user->setEnabled(true);
            }
        }

        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->add('save', SubmitType::class, [
            'label'  => 'registration.submit',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->handleInvitationConfirmed($request, $user, $invitation,
                $objectManager, $userManager, $eventDispatcher, $canonicalizer
            );
        }

        return $this->render('_partials/profile/definition_password_for_classical_users.html.twig', [
            'form' => $form->createView(),
            'invitationUser' => $invitation->getUser(),
            'businessAccountInvitation' => $businessAccountInvitation
        ]);
    }

    /**
     * @Route("/invitation/associate-loggedin-user-to-business-account/{code}", name="associate-loggedin-user-to-business-account")
     */
    public function associateLoggedinUserToBusinessAccount(
        string $code,
        EntityManagerInterface $objectManager,
        UserManagerInterface $userManager,
        TranslatorInterface $translator)
    {
        $user = $this->getUser();

        $repository = $objectManager->getRepository(Invitation::class);

        if (null === $invitation = $repository->findOneByCode($code)) {
            throw $this->createNotFoundException();
        }

        $businessAccountInvitation = null;
        if ($this->getParameter('business_account_enabled')) {
            $businessAccountInvitation = $objectManager->getRepository(BusinessAccountInvitation::class)->findOneBy([
                'invitation' => $invitation,
            ]);
            if (null !== $businessAccountInvitation) {
                $user->setBusinessAccount($businessAccountInvitation->getBusinessAccount());
            }
        }

        $userManager->updateUser($user);
        $objectManager->flush();

        $this->addFlash(
            'notice',
            $translator->trans('business_account.employee.associated', [
                '%name%' => $businessAccountInvitation->getBusinessAccount()->getName()
            ])
        );

        return $this->redirectToRoute('homepage');
    }

    private function loadBusinessAccountRegistrationFlow(Request $request,
        BusinessAccountRegistrationFlow $businessAccountRegistrationFlow,
        User $user,
        BusinessAccountInvitation $businessAccountInvitation,
        EntityManagerInterface $objectManager,
        UserManagerInterface $userManager,
        EventDispatcherInterface $eventDispatcher,
        Canonicalizer $canonicalizer,
        TokenGeneratorInterface $tokenGenerator
    )
    {
        $flowData = new BusinessAccountRegistration($user, $businessAccountInvitation->getBusinessAccount());
        $businessAccountRegistrationFlow->bind($flowData);
        $form = $submittedForm = $businessAccountRegistrationFlow->createForm();

        if ($businessAccountRegistrationFlow->isValid($submittedForm)) {
            $businessAccountRegistrationFlow->saveCurrentStepData($submittedForm);

            if ($businessAccountRegistrationFlow->nextStep()) {
                // form for the next step
                $form = $businessAccountRegistrationFlow->createForm();
            } else {
                $invitation = new Invitation();
                $invitation->setEmail($canonicalizer->canonicalize($user->getEmail()));
                $invitation->setUser($user);
                $invitation->setCode($tokenGenerator->generateToken());

                $businessAccountEmployeeInvitation = new BusinessAccountInvitation();
                $businessAccountEmployeeInvitation->setBusinessAccount($businessAccountInvitation->getBusinessAccount());
                $businessAccountEmployeeInvitation->setInvitation($invitation);

                $response = $this->handleInvitationConfirmed($request, $flowData->user, $businessAccountInvitation->getInvitation(),
                    $objectManager, $userManager, $eventDispatcher, $canonicalizer
                );

                $objectManager->persist($businessAccountEmployeeInvitation);
                $objectManager->flush();

                $businessAccountRegistrationFlow->reset(); // remove step data from the session

                return $response;
            }
        }

        return $this->render('_partials/profile/definition_password_for_business_account.html.twig', [
            'form' => $form->createView(),
            'flow' => $businessAccountRegistrationFlow,
            'businessAccountInvitation' => $businessAccountInvitation,
        ]);
    }

    private function handleInvitationConfirmed(
        Request $request,
        User $user,
        Invitation $invitation,
        EntityManagerInterface $objectManager,
        UserManagerInterface $userManager,
        EventDispatcherInterface $eventDispatcher,
        Canonicalizer $canonicalizer)
    {
        $existingCustomer = $objectManager->getRepository(Customer::class)
            ->findOneBy([
                'emailCanonical' => $canonicalizer->canonicalize($user->getEmail())
            ]);

        if (null !== $existingCustomer) {
            $user->setCustomer($existingCustomer);
        }

        if ($grants = $invitation->getGrants()) {
            if (isset($grants['roles'])) {
                foreach ($grants['roles'] as $role) {
                    $user->addRole($role);
                }
            }
            if (isset($grants['restaurants'])) {
                foreach ($grants['restaurants'] as $restaurantId) {
                    if ($restaurant = $objectManager->getRepository(LocalBusiness::class)->find($restaurantId)) {
                        $user->addRestaurant($restaurant);
                        $user->addRole('ROLE_RESTAURANT');
                    }

                }
            }
            if (isset($grants['stores'])) {
                foreach ($grants['stores'] as $storeId) {
                    if ($store = $objectManager->getRepository(Store::class)->find($storeId)) {
                        $user->addStore($store);
                        $user->addRole('ROLE_STORE');
                    }
                }
            }
        }

        $businessAccountInvitation = null;
        if ($this->getParameter('business_account_enabled')) {
            $businessAccountInvitation = $objectManager->getRepository(BusinessAccountInvitation::class)->findOneBy([
                'invitation' => $invitation,
            ]);
            if (null !== $businessAccountInvitation) {
                $user->setBusinessAccount($businessAccountInvitation->getBusinessAccount());
            }
        }

        if (null === $businessAccountInvitation || $businessAccountInvitation->isInvitationForManager()) {
            if (null !== $businessAccountInvitation && $businessAccountInvitation->isInvitationForManager()) {
                $objectManager->remove($businessAccountInvitation);
            }

            $userManager->updateUser($user);

            $objectManager->remove($invitation);
            $objectManager->flush();
        } else {
            $userManager->updateUser($user);
            $objectManager->flush();
        }

        $parameters = [];

        if ($businessAccountInvitation) {
            $parameters['_business'] = true;
        }

        $response = new RedirectResponse($this->generateUrl('nucleos_profile_registration_confirmed', $parameters));

        $eventDispatcher->dispatch(new FilterUserResponseEvent($user, $request, $response), NucleosProfileEvents::REGISTRATION_CONFIRMED);

        return $response;
    }
}
