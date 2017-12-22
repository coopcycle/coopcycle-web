<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/{_locale}", requirements={ "_locale": "%locale_regex%" })
 */
class HelpController extends Controller
{
    private function resolveTemplate($name, $locale)
    {
        return "templates/$name.{$locale}.md";
    }

    /**
     * @Route("/help", name="help")
     * @Template()
     */
    public function indexAction(Request $request)
    {
        return [
            'template' => $this->resolveTemplate('index', $request->getLocale())
        ];
    }

    /**
     * @Route("/help/admin/roles", name="help_admin_roles")
     * @Template("@App/Help/index.html.twig")
     */
    public function adminRolesAction(Request $request)
    {
        return [
            'template' => $this->resolveTemplate('admin/roles/index', $request->getLocale())
        ];
    }
}
