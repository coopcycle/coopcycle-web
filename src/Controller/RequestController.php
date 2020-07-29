<?php
declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Form\Restaurant\RequestForAddType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

/**
 * @Route("/{_locale}/request")
 */
class RequestController
{
    private Environment $twig;
    private FormFactoryInterface $formFactory;
    /**
     * @var MessageBusInterface
     */
    private MessageBusInterface $bus;

    public function __construct(Environment $twig, FormFactoryInterface $formFactory, MessageBusInterface $bus)
    {
        $this->twig = $twig;
        $this->formFactory = $formFactory;
        $this->bus = $bus;
    }

    /**
     * @Route("/restaurant", name="request_restaurant")
     */
    public function restaurantAction(Request $request)
    {

        $form = $this->formFactory->create(RequestForAddType::class);
        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
            $requestRestaurant = $form->getData();
            $this->bus->dispatch($requestRestaurant);

        }

        return new Response(
            $this->twig->render('request/restaurant.html.twig',
                [
                    'form' => $form->createView(),
                ]
            )
        );
    }
}
