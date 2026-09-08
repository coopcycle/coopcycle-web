<?php

namespace AppBundle\Command;

use AppBundle\Entity\ExportWatermark;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reports what this instance *should* have exported, so that it can be compared
 * with what actually reached ClickHouse.
 *
 * The two sides of the pipeline cannot see each other: an instance holds the
 * source data but has no access to the warehouse, and the warehouse has no
 * access to the instances. This command produces the instance half of that
 * comparison, per month, and the state of the export itself.
 *
 * Nobody noticed that 42 instances had stopped landing data for a week; this is
 * the check that makes that visible.
 */
class ExportAuditCommand extends Command
{
    public function __construct(
        private readonly string $appName,
        private readonly EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('coopcycle:export:audit')
            ->setDescription('Report per-month task and order counts, and the state of the incremental export')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'table or json', 'table')
            ->addOption('months', null, InputOption::VALUE_REQUIRED, 'How many months back to report', 6);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $months = intval($input->getOption('months'));

        $report = [
            'instance' => $this->appName,
            'generated_at' => (new \DateTime())->format(\DateTimeInterface::ATOM),
            'exports' => $this->getExportState(),
            'tasks' => $this->getCountsByMonth('task', 'done_after', $months),
            'orders' => $this->getCountsByMonth('sylius_order', 'created_at', $months),
        ];

        if ('json' === $input->getOption('format')) {
            $output->writeln(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Export audit for %s', $this->appName));

        $io->section('Export state');
        $io->table(
            ['dataset', 'watermark', 'last run', 'rows', 'rows only from overlap'],
            array_map(fn ($row) => [
                $row['type'],
                $row['watermark_at'] ?? 'never',
                $row['last_run_at'] ?? 'never',
                $row['rows'],
                $row['overlap_rows'],
            ], $report['exports'])
        );

        foreach (['tasks', 'orders'] as $dataset) {
            $io->section(sprintf('%s per month', ucfirst($dataset)));
            $io->table(
                ['month', 'rows'],
                array_map(fn ($m, $c) => [$m, $c], array_keys($report[$dataset]), $report[$dataset])
            );
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getExportState(): array
    {
        $repository = $this->entityManager->getRepository(ExportWatermark::class);

        $state = [];
        foreach ([ExportWatermark::TYPE_TASKS, ExportWatermark::TYPE_ORDERS] as $type) {
            /** @var ExportWatermark|null $watermark */
            $watermark = $repository->find($type);

            $state[] = [
                'type' => $type,
                'watermark_at' => $watermark?->getWatermarkAt()?->format(\DateTimeInterface::ATOM),
                'last_run_at' => $watermark?->getLastRunAt()?->format(\DateTimeInterface::ATOM),
                'rows' => $watermark?->getLastRunRows() ?? 0,
                'overlap_rows' => $watermark?->getLastRunOverlapRows() ?? 0,
            ];
        }

        return $state;
    }

    /**
     * @return array<string, int>
     */
    private function getCountsByMonth(string $table, string $dateColumn, int $months): array
    {
        // The table and column are not user input, they are the two literals
        // this command was written for.
        $sql = sprintf(
            'SELECT to_char(%1$s, \'YYYY-MM\') AS month, COUNT(*) AS rows
             FROM %2$s
             WHERE %1$s >= :since
             GROUP BY month
             ORDER BY month DESC',
            $dateColumn,
            $table
        );

        $since = (new \DateTime())->modify(sprintf('-%d months', $months))->format('Y-m-01');

        $rows = $this->entityManager->getConnection()
            ->executeQuery($sql, ['since' => $since])
            ->fetchAllAssociative();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['month']] = intval($row['rows']);
        }

        return $counts;
    }
}
