<?php

namespace AppBundle\Controller;

use AppBundle\Form\AppearanceType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SettingsController extends Controller
{
    /**
     * @Route("/admin/settings/appearance", name="admin_settings_appearance")
     * @Template
     */
    public function appearanceAction(Request $request)
    {
        $imagesDir = $this->getParameter('kernel.root_dir'). '/../web/images/custom';

        $settings = $this->get('craue_config')->getBySection('appearance');

        $form = $this->createForm(AppearanceType::class, $settings, [
            'images_dir' => $imagesDir
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $data = $form->getData();

            if ($data['jumbotronBackgroundImage']) {

                $jumbotronBackgroundImageFile = $data['jumbotronBackgroundImage'];
                unset($data['jumbotronBackgroundImage']);

                if ('image/svg+xml' === $jumbotronBackgroundImageFile->getClientMimeType()) {

                    if (!file_exists($imagesDir)) {
                        mkdir($imagesDir, 0755);
                    }

                    $fileName = md5(uniqid()).'.svg';
                    $jumbotronBackgroundImageFile->move($imagesDir, $fileName);

                    $data['jumbotronBackgroundImage'] = $fileName;
                }
            }

            foreach ($data as $name => $value) {
                $this->get('coopcycle.settings')->set($name, $value, 'appearance');
            }

            return $this->redirectToRoute('admin_settings_appearance');
        }

        return [
            'form' => $form->createView(),
        ];
    }

    /**
     * @Route("/css/custom.css", name="settings_custom_css")
     */
    public function customCssAction(Request $request)
    {
        $response = new Response();

        $response->headers->set('Content-Type', 'text/css');

        $settings = $this->get('craue_config')->getBySection('appearance');

        return $this->render('@App/Settings/customCss.css.twig', [
            'settings' => $settings
        ], $response);
    }
}
