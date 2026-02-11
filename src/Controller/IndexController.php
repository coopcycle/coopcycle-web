<?php

namespace AppBundle\Controller;

use AppBundle\Annotation\HideSoftDeleted;
use Sylius\Component\Order\Context\CartContextInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

class IndexController extends AbstractController
{
    // TODO Add this attribute to Twig components
    #[HideSoftDeleted]
    public function indexAction()
    {
        // Everything is in Twig components
        // @see src/Twig/Components/Homepage.php
        return $this->render('index/index.html.twig');
    }

    #[Route(path: '/cart.json', name: 'cart_json')]
    public function cartAsJsonAction(CartContextInterface $cartContext)
    {
        $cart = $cartContext->getCart();

        $data = [
            'itemsTotal' => $cart->getItemsTotal(),
            'total' => $cart->getTotal(),
        ];

        return new JsonResponse($data);
    }

    #[Route(path: '/CHANGELOG.md', name: 'changelog')]
    public function changelogAction()
    {
        $response = new Response(file_get_contents($this->getParameter('kernel.project_dir') . '/CHANGELOG.md'));
        $response->headers->add(['Content-Type' => 'text/markdown']);
        return $response;
    }

    public function redirectToLocaleAction()
    {
        return new RedirectResponse(sprintf('/%s/', $this->getParameter('locale')), 302);
    }
}
