<?php

namespace AppBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'coopcycle:recommender:train',
    description: 'Push order history to the recommender service and train the model for this instance.',
)]
class RecommenderTrainCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire(service: 'recommender.client')] private readonly HttpClientInterface $recommenderClient,
        #[Autowire('%database_name%')] private readonly string $databaseName,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Training recommender for instance "%s"', $this->databaseName));

        $io->section('Fetching product interactions...');
        $productInteractions = $this->connection->fetchAllAssociative('
            SELECT o.customer_id, pv.product_id AS item_id, COUNT(*) AS interaction_count
            FROM sylius_order_item oi
            JOIN sylius_order o ON o.id = oi.order_id
            JOIN sylius_product_variant pv ON pv.id = oi.variant_id
            JOIN sylius_product p ON p.id = pv.product_id
            WHERE o.state = :state
              AND o.customer_id IS NOT NULL
              AND p.deleted_at IS NULL
              AND p.enabled = TRUE
              AND p.restaurant_id IS NOT NULL
            GROUP BY o.customer_id, pv.product_id
        ', ['state' => 'fulfilled']);

        $productPopular = $this->connection->fetchFirstColumn('
            SELECT pv.product_id
            FROM sylius_order_item oi
            JOIN sylius_order o ON o.id = oi.order_id
            JOIN sylius_product_variant pv ON pv.id = oi.variant_id
            JOIN sylius_product p ON p.id = pv.product_id
            WHERE o.state = :state
              AND p.deleted_at IS NULL
              AND p.enabled = TRUE
              AND p.restaurant_id IS NOT NULL
            GROUP BY pv.product_id
            ORDER BY COUNT(*) DESC
            LIMIT 20
        ', ['state' => 'fulfilled']);

        $io->writeln(sprintf('  Found %d product interactions, %d popular products.', count($productInteractions), count($productPopular)));

        $io->section('Fetching restaurant interactions...');
        $restaurantInteractions = $this->connection->fetchAllAssociative('
            SELECT o.customer_id, ov.restaurant_id AS item_id, COUNT(*) AS interaction_count
            FROM sylius_order_vendor ov
            JOIN sylius_order o ON o.id = ov.order_id
            WHERE o.state = :state
              AND o.customer_id IS NOT NULL
            GROUP BY o.customer_id, ov.restaurant_id
        ', ['state' => 'fulfilled']);

        $restaurantPopular = $this->connection->fetchFirstColumn('
            SELECT ov.restaurant_id
            FROM sylius_order_vendor ov
            JOIN sylius_order o ON o.id = ov.order_id
            WHERE o.state = :state
            GROUP BY ov.restaurant_id
            ORDER BY COUNT(*) DESC
            LIMIT 10
        ', ['state' => 'fulfilled']);

        $io->writeln(sprintf('  Found %d restaurant interactions, %d popular restaurants.', count($restaurantInteractions), count($restaurantPopular)));

        $io->section('Pushing to recommender...');
        try {
            $response = $this->recommenderClient->request('POST', '/train', [
                'json' => [
                    'instance'                 => $this->databaseName,
                    'product_interactions'     => $productInteractions,
                    'product_popular'          => array_map('intval', $productPopular),
                    'restaurant_interactions'  => $restaurantInteractions,
                    'restaurant_popular'       => array_map('intval', $restaurantPopular),
                ],
            ]);
            $data = $response->toArray();
            $io->success(sprintf(
                'Training complete for instance "%s" (trained at %s).',
                $data['instance'],
                $data['trained_at'],
            ));
        } catch (\Throwable $e) {
            $io->error(sprintf('Failed to push to recommender: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
