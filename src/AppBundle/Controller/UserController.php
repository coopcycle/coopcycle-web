<?php

namespace AppBundle\Controller;

use AppBundle\Entity\ApiUser;
use FOS\UserBundle\Model\UserManagerInterface;
use FOS\UserBundle\Mailer\MailerInterface;
use Laravolt\Avatar\Avatar;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use AppBundle\Entity\Invitation;
use AppBundle\Form\SetPasswordInvitationType;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

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

    /**
     * @Route("/users/{username}", name="user")
     * @Template()
     */
    public function indexAction($username, UserManagerInterface $userManager)
    {
        $user = $userManager->findUserByUsername($username);

        return [
            'user' => $user,
        ];
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
    public function definePasswordToFinishInvitationRegistration(Request $request, string $code, UserManagerInterface $userManager, UserPasswordEncoderInterface $passwordEncoder)
    {

        $form = $this->createForm(SetPasswordInvitationType::class);
        $form->handleRequest($request);

        $em = $this->getDoctrine()->getManager();

        if(!$em->getRepository(Invitation::class)->findOneByCode($code)) {
            return $this->redirectToRoute('fos_user_security_login');
        }

        if ($form->isSubmitted() && $form->isValid()) {

            $invitation = $em->getRepository(Invitation::class)->findOneByCode($code);
            $user = $userManager->findUserByEmail($invitation->getEmail());

            if (!$user) {
                throw $this->createNotFoundException(
                    'No user found for id '.$user->getId()
                );
            }

            $password = $passwordEncoder->encodePassword($user, $form->get('plainPassword')->getData());
            $user->setPassword($password);
            $user->setInvitation(null);

            $this->getDoctrine()->getManagerForClass(ApiUser::class)->persist($user);
            $this->getDoctrine()->getManagerForClass(Invitation::class)->remove($invitation);
            $this->getDoctrine()->getManagerForClass(ApiUser::class)->flush();

            return $this->redirectToRoute('fos_user_security_login');
        }

        return $this->render('@App/profile/invitation_define_password.html.twig', [
            'form' => $form->createView()
        ]);
    }
}
