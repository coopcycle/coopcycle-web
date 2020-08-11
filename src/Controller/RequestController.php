<?php
declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Form\Restaurant\RequestForAddType;
use AppBundle\Message\Email;
use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
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

    public function __construct(
        Environment $twig,
        FormFactoryInterface $formFactory,
        TranslatorInterface $translator,
        RouterInterface $router,
        SettingsManager $settingsManager,
        EmailManager $emailManager,
        MessageBusInterface $bus)
    {
        $this->twig = $twig;
        $this->formFactory = $formFactory;
        $this->translator = $translator;
        $this->router = $router;
        $this->settingsManager = $settingsManager;
        $this->emailManager = $emailManager;
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

            $message = $this->emailManager->createHtmlMessage(
                $this->translator->trans('registration.restaurant', [], 'emails'),
                $this->twig->render('emails/request/restaurant.mjml.twig', [
                    'restaurant' => $requestRestaurant,
                ])
            );

            $this->bus->dispatch(new Email(
                $message,
                $this->settingsManager->get('administrator_email')
            ));

            return new RedirectResponse($this->router->generate('request_restaurant'));
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
