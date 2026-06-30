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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(
    name: 'coopcycle:shopify:register-webhooks',
    description: 'Register (or re-register) Shopify webhooks for all installed shops.',
)]
class ShopifyRegisterWebhooksCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ShopifyClient $shopifyClient,
        private UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('webhook-url', InputArgument::OPTIONAL, 'Override the webhook URL (e.g. https://your-ngrok.ngrok-free.app/api/shopify/webhook)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $shops = $this->entityManager->getRepository(ShopifyShop::class)->findAll();

        if (empty($shops)) {
            $io->info('No Shopify shops found.');
            return Command::SUCCESS;
        }

        $webhookUrl = $input->getArgument('webhook-url')
            ?? $this->urlGenerator->generate('_api_/shopify/webhook_post', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $io->info(sprintf('Webhook URL: %s', $webhookUrl));

        $ok  = 0;
        $err = 0;

        foreach ($shops as $shop) {
            if (str_ends_with($shop->getShopDomain(), 'feature-preview.myshopify.com')) {
                $io->note(sprintf('Skipping sandbox domain %s', $shop->getShopDomain()));
                continue;
            }

            foreach (['orders/create', 'orders/cancelled'] as $topic) {
                $result = $this->shopifyClient->registerWebhook($shop, $topic, $webhookUrl);
                if ($result !== null) {
                    $io->success(sprintf('[%s] Registered "%s"', $shop->getShopDomain(), $topic));
                    $ok++;
                } else {
                    $io->error(sprintf('[%s] Failed to register "%s"', $shop->getShopDomain(), $topic));
                    $err++;
                }
            }
        }

        $io->info(sprintf('Done: %d registered, %d failed.', $ok, $err));

        return $err > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
