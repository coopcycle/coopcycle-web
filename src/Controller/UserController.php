<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Invitation;
use AppBundle\Form\SetPasswordInvitationType;
use AppBundle\Sylius\Order\OrderFactory;
use Cocur\Slugify\SlugifyInterface;
use Doctrine\ORM\EntityManagerInterface;
use FOS\UserBundle\Event\FilterUserResponseEvent;
use FOS\UserBundle\FOSUserEvents;
use FOS\UserBundle\Mailer\MailerInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use FOS\UserBundle\Util\UserManipulator;
use Laravolt\Avatar\Avatar;
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
    private $avatarBackgrounds = [
        '#f44336',
        '#E91E63',
        '#9C27B0',
        '#673AB7',
        '#3F51B5',
        '#2196F3',
        '#03A9F4',
        '#00BCD4',
        '#009688',
        '#4CAF50',
        '#8BC34A',
        '#CDDC39',
        '#FFC107',
        '#FF9800',
        '#FF5722',
    ];

    private function createSuggestions(UserManagerInterface $userManager, SlugifyInterface $slugify, $email, $index = 0)
    {
        if (empty($email)) {
            return [];
        }

        if (false !== strpos($email, '@')) {
            $parts = explode('@', $email);
            $email = $parts[0];
        }

        $username = $slugify->slugify($email, ['separator' => '_']);

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
        $dir = $this->getParameter('kernel.project_dir') . '/web/images/avatars/';

        if (!file_exists($dir)) {
            mkdir($dir, 0755);
        }
        $avatar = new Avatar([
            'uppercase' => true,
            'backgrounds' => $this->avatarBackgrounds
        ]);

        $avatar
            ->create($username)
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
    public function resendRegistrationEmailAction(Request $request, UserManagerInterface $userManager, MailerInterface $mailer, SessionInterface $session)
    {
        if ($request->request->has('email')) {
            $email = $request->request->get('email');
            $user = $userManager->findUserByEmail($email);
            if ($user !== null) {
                $mailer->sendConfirmationEmailMessage($user);
                $session->set('fos_user_send_confirmation_email/email', $email);
                return $this->redirectToRoute('fos_user_registration_check_email');
            }
        }
        return $this->redirectToRoute('fos_user_security_login');
    }

    /**
     * @Route("/invitation/define-password/{code}", name="invitation_define_password")
     */
    public function confirmInvitationAction(Request $request, string $code,
        EntityManagerInterface $objectManager,
        UserManipulator $userManipulator,
        EventDispatcherInterface $eventDispatcher)
    {
        $repository = $this->getDoctrine()->getRepository(Invitation::class);

        if (null === $invitation = $repository->findOneByCode($code)) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(SetPasswordInvitationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $user = $invitation->getUser();

            $userManipulator->changePassword($user->getUsername(), $form->get('plainPassword')->getData());

            $objectManager->remove($invitation);
            $objectManager->flush();

            $response = new RedirectResponse($this->generateUrl('fos_user_registration_confirmed'));

            $eventDispatcher->dispatch(FOSUserEvents::REGISTRATION_CONFIRMED, new FilterUserResponseEvent($user, $request, $response));

            return $response;
        }

        return $this->render('profile/invitation_define_password.html.twig', [
            'form' => $form->createView()
        ]);
    }
}
