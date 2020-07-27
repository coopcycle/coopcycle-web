<?php
declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Form\Company\RegistrationType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * Class CompanyController
 * @Route("/company")
 */
class CompanyController
{
    private MessageBusInterface $bus;
    /**
     * @var FormFactoryInterface
     */
    private FormFactoryInterface $formFactory;
    /**
     * @var Environment
     */
    private Environment $twig;
    /**
     * @var RouterInterface
     */
    private RouterInterface $router;

    public function __construct(
        MessageBusInterface $bus,
        FormFactoryInterface $formFactory,
        Environment $twig,
        RouterInterface $router
    ) {
        $this->bus = $bus;
        $this->formFactory = $formFactory;
        $this->twig = $twig;
        $this->router = $router;
    }

    /**
     * @Route("/register" , name="company_registration")
     */
    public function registerAction(Request $request)
    {

        $form = $this->formFactory->create(RegistrationType::class);
        if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {

            $companyRegistration = $form->getData();
            $this->bus->dispatch($companyRegistration);

            return new RedirectResponse($this->router->generate('company_registration'));
        }

        return new Response($this->twig->render(
            'company/register.html.twig',
            [
                'form' => $form->createView(),
            ]
        ));
    }
}
