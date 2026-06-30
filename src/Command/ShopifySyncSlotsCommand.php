<?php

namespace AppBundle\Command;

use AppBundle\Entity\Shopify\ShopifyShop;
use AppBundle\Service\ShopifyClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'coopcycle:shopify:sync-slots',
    description: 'Write the OpeningHoursSpecification and tenant URL of each Shopify shop as shop metafields.',
)]
class ShopifySyncSlotsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ShopifyClient $shopifyClient,
        private string $tenantUrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('tenant-url', InputArgument::OPTIONAL, 'Override the tenant URL (e.g. https://your-ngrok.ngrok-free.app)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $shops = $this->entityManager->getRepository(ShopifyShop::class)->findAll();

        if (empty($shops)) {
            $io->info('No Shopify shops found.');
            return Command::SUCCESS;
        }

        $ok  = 0;
        $err = 0;

        foreach ($shops as $shop) {
            $store = $shop->getStore();

            if (!$store) {
                $io->warning(sprintf('Shop %s has no store — skipping.', $shop->getShopDomain()));
                continue;
            }

            $timeSlot = $store->getTimeSlot();

            if (!$timeSlot) {
                $io->warning(sprintf('Shop %s store has no time slot — skipping.', $shop->getShopDomain()));
                continue;
            }

            $spec = $timeSlot->getOpeningHoursSpecification();

            $tenantUrl = $input->getArgument('tenant-url') ?? $this->tenantUrl;
            $this->shopifyClient->syncTenantUrl($shop, $tenantUrl);

            if ($this->shopifyClient->syncSlotsSpec($shop, $spec)) {
                $io->success(sprintf('Synced slots spec for %s.', $shop->getShopDomain()));
                $ok++;
            } else {
                $io->error(sprintf('Failed to sync slots spec for %s.', $shop->getShopDomain()));
                $err++;
            }
        }

        $io->info(sprintf('Done: %d synced, %d failed.', $ok, $err));

        return $err > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
