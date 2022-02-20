<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Invitation;
use AppBundle\Entity\LocalBusiness;
use AppBundle\Entity\Store;
use AppBundle\Sylius\Order\OrderFactory;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\ProfileBundle\NucleosProfileEvents;
use AppBundle\Form\Model\Registration;
use Nucleos\ProfileBundle\Mailer\MailerInterface as ProfileMailerInterface;
use Nucleos\UserBundle\Event\FilterUserResponseEvent;
use Nucleos\UserBundle\Model\UserManagerInterface;
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
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
    public function resendRegistrationEmailAction(Request $request, UserManagerInterface $userManager, ProfileMailerInterface $mailer, SessionInterface $session)
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
        EventDispatcherInterface $eventDispatcher)
    {
        $repository = $this->getDoctrine()->getRepository(Invitation::class);

        if (null === $invitation = $repository->findOneByCode($code)) {
            throw $this->createNotFoundException();
        }

        $registration = new Registration();
        $registration->setEmail($invitation->getEmail());

        $form = $this->createForm(RegistrationFormType::class, $registration);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $user = $registration->toUser($userManager);

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

            $userManager->updateUser($user);

            $objectManager->remove($invitation);
            $objectManager->flush();

            $response = new RedirectResponse($this->generateUrl('nucleos_profile_registration_confirmed'));

            $eventDispatcher->dispatch(new FilterUserResponseEvent($user, $request, $response), NucleosProfileEvents::REGISTRATION_CONFIRMED);

            return $response;
        }

        return $this->render('profile/invitation_define_password.html.twig', [
            'form' => $form->createView()
        ]);
    }
}
