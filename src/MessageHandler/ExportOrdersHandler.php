<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\ExportOrders;
use AppBundle\Sylius\Taxation\TaxesHelper;
use AppBundle\Utils\RestaurantStats;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExportOrdersHandler implements MessageHandlerInterface
{

    public function __construct(
        private TranslatorInterface $translator,
        private EntityManagerInterface $entityManager,
        private PaginatorInterface $paginator,
        private TaxesHelper $taxesHelper,
        private ContainerInterface $container
    )
    { }

    public function __invoke(ExportOrders $message): ?string
    {
        $locale = $message->getLocale() ?? $this->container->getParameter('kernel.default_locale');
        $stats = new RestaurantStats(
            $this->entityManager,
            $message->getFrom()->setTime(0, 0, 0),
            $message->getTo()->setTime(23, 59, 59),
            null,
            $this->paginator,
            $locale,
            $this->translator,
            $this->taxesHelper,
            true,
            $message->isWithMessenger(),
            $this->container->getParameter('nonprofits_enabled'),
            $message->isWithBillingMethod()
        );

        if ($stats->count() === 0) {
            return null;
        }
        return $stats->toCsv();
    }
}
