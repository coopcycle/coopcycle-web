<?php

namespace AppBundle\Controller;

use AppBundle\Entity\ApiUser;
use Laravolt\Avatar\Avatar;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route as Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

class UserController extends Controller
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
    public function indexAction($username)
    {
        // @link https://symfony.com/doc/current/bundles/FOSUserBundle/user_manager.html
        $userManager = $this->get('fos_user.user_manager');

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
}
