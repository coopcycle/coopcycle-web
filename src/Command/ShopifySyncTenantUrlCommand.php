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

/**
 * Backfills the `coopcycle.tenant_url` app-data metafield, which the cart date
 * picker reads to know which cooperative to ask for delivery slots.
 *
 * It is normally written at install time, so this is for the cases where that
 * did not stick: a tenant that changed its public URL, an install whose write
 * failed, or a shop installed before the metafield was written to the app
 * installation rather than to the shop.
 */
#[AsCommand(
    name: 'coopcycle:shopify:sync-tenant-url',
    description: 'Write this instance\'s URL to each Shopify shop as an app-data metafield.',
)]
class ShopifySyncTenantUrlCommand extends Command
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

        // The URL identifies this CoopCycle instance, not a particular store, so
        // every installed shop gets it — no store or time slot needed.
        $tenantUrl = $input->getArgument('tenant-url') ?? $this->tenantUrl;

        $io->info(sprintf('Tenant URL: %s', $tenantUrl));

        $ok  = 0;
        $err = 0;

        foreach ($shops as $shop) {
            if ($this->shopifyClient->syncTenantUrl($shop, $tenantUrl)) {
                $io->success(sprintf('Synced tenant URL for %s.', $shop->getShopDomain()));
                $ok++;
            } else {
                $io->error(sprintf('Failed to sync tenant URL for %s.', $shop->getShopDomain()));
                $err++;
            }
        }

        $io->info(sprintf('Done: %d synced, %d failed.', $ok, $err));

        return $err > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
