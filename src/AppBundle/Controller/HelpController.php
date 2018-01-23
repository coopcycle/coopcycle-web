<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HelpController extends Controller
{
    private function resolveTemplate($name, $locale)
    {
        return "templates/$name.{$locale}.md";
    }

    private function getMenu()
    {
        $menu = [];
        foreach ($this->get('router')->getRouteCollection()->all() as $name => $route) {
            if (strpos($name, 'help_') === 0) {
                $defaults = $route->getDefaults();
                $menu[$name] = $defaults['title'];
            }
        }

        return $menu;
    }

    /**
     * @Template()
     */
    public function indexAction(Request $request)
    {
        return [
            'menu' => $this->getMenu(),
            'template' => $this->resolveTemplate('index', $request->getLocale()),
        ];
    }

    /**
     * @Template("@App/Help/index.html.twig")
     */
    public function renderMarkdownAction(Request $request)
    {
        return [
            'menu' => $this->getMenu(),
            'template' => $this->resolveTemplate($request->attributes->get('template'), $request->getLocale())
        ];
    }
}
