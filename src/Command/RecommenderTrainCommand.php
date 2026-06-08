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
    private const CHUNK_SIZE = 1000;

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

        try {
            $this->recommenderClient->request('POST', '/train/start', [
                'json' => ['instance' => $this->databaseName],
            ])->getStatusCode();
        } catch (\Throwable $e) {
            $io->error(sprintf('Cannot reach recommender: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        $io->section('Pushing product interactions...');
        $productCount = $this->streamInteractions($io, 'product', '
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
        ');

        $io->section('Pushing restaurant interactions...');
        $restaurantCount = $this->streamInteractions($io, 'restaurant', '
            SELECT o.customer_id, ov.restaurant_id AS item_id, COUNT(*) AS interaction_count
            FROM sylius_order_vendor ov
            JOIN sylius_order o ON o.id = ov.order_id
            WHERE o.state = :state
              AND o.customer_id IS NOT NULL
            GROUP BY o.customer_id, ov.restaurant_id
        ');

        $productPopular = array_map('intval', $this->connection->fetchFirstColumn('
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
        ', ['state' => 'fulfilled']));

        $restaurantPopular = array_map('intval', $this->connection->fetchFirstColumn('
            SELECT ov.restaurant_id
            FROM sylius_order_vendor ov
            JOIN sylius_order o ON o.id = ov.order_id
            WHERE o.state = :state
            GROUP BY ov.restaurant_id
            ORDER BY COUNT(*) DESC
            LIMIT 10
        ', ['state' => 'fulfilled']));

        $io->section('Fetching product→restaurant map...');
        $productRestaurantRows = $this->connection->fetchAllKeyValue('
            SELECT id, restaurant_id
            FROM sylius_product
            WHERE restaurant_id IS NOT NULL
              AND enabled = TRUE
              AND deleted_at IS NULL
        ');
        // Cast keys and values to int; JSON object keys will be strings on the Python side
        $productRestaurantMap = array_map('intval', $productRestaurantRows);
        $io->writeln(sprintf('  Mapped %d products to restaurants.', count($productRestaurantMap)));

        if ($productCount === 0 && $restaurantCount === 0) {
            $io->warning('No interactions found — skipping commit.');
            return Command::SUCCESS;
        }

        $io->section('Committing training...');
        try {
            $response = $this->recommenderClient->request('POST', '/train/commit', [
                'json' => [
                    'instance'               => $this->databaseName,
                    'product_popular'        => $productPopular,
                    'restaurant_popular'     => $restaurantPopular,
                    'product_restaurant_map' => $productRestaurantMap,
                ],
            ]);
            $data = $response->toArray();
            $io->success(sprintf(
                'Training complete for instance "%s": %d product interactions, %d restaurant interactions (trained at %s).',
                $data['instance'],
                $data['product_interactions'],
                $data['restaurant_interactions'],
                $data['trained_at'],
            ));
        } catch (\Throwable $e) {
            $io->error(sprintf('Commit failed: %s', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function streamInteractions(SymfonyStyle $io, string $type, string $sql): int
    {
        $result = $this->connection->executeQuery($sql, ['state' => 'fulfilled']);
        $chunk = [];
        $total = 0;
        $chunkIndex = 0;

        while ($row = $result->fetchAssociative()) {
            $chunk[] = [
                'customer_id'       => (int) $row['customer_id'],
                'item_id'           => (int) $row['item_id'],
                'interaction_count' => (int) $row['interaction_count'],
            ];

            if (count($chunk) === self::CHUNK_SIZE) {
                $this->pushChunk($type, $chunk, ++$chunkIndex, $io);
                $total += count($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            $this->pushChunk($type, $chunk, ++$chunkIndex, $io);
            $total += count($chunk);
        }

        $io->writeln(sprintf('  Pushed %d %s interactions in %d chunk(s).', $total, $type, $chunkIndex));

        return $total;
    }

    private function pushChunk(string $type, array $chunk, int $index, SymfonyStyle $io): void
    {
        $io->writeln(sprintf('  Chunk %d: %d rows...', $index, count($chunk)), OutputInterface::VERBOSITY_VERBOSE);

        $this->recommenderClient->request('POST', '/train/push', [
            'json' => [
                'instance'     => $this->databaseName,
                'type'         => $type,
                'interactions' => $chunk,
            ],
        ])->getStatusCode();
    }
}
