<?php

namespace AppBundle\MessageHandler;

use AppBundle\Message\ExportOrders;
use AppBundle\Sylius\Taxation\TaxesHelper;
use AppBundle\Utils\RestaurantStats;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler]
class ExportOrdersHandler
{

    public function __construct(
        private TranslatorInterface $translator,
        private EntityManagerInterface $entityManager,
        private PaginatorInterface $paginator,
        private TaxesHelper $taxesHelper,
        private string $defaultLocale,
        private bool $nonProfitsEnabled
    )
    { }

    public function __invoke(ExportOrders $message): ?string
    {
        $locale = $message->getLocale() ?? $this->defaultLocale;
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
            $this->nonProfitsEnabled,
            $message->isWithBillingMethod(),
            $message->isIncludeTaxes()
        );

        if ($stats->count() === 0) {
            return null;
        }
        return $stats->toCsv();
    }
}
