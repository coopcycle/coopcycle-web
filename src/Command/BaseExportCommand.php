<?php

namespace AppBundle\Command;

use AppBundle\Entity\ExportWatermark;
use Aws\S3\S3Client;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

abstract class BaseExportCommand extends Command {

    use LockableTrait;

    /**
     * Files written by the incremental exports live under this prefix, below
     * whatever prefix --target points at, so that they stay apart from the
     * legacy day-per-file exports and both pipelines can run side by side
     * during the migration.
     *
     * The bucket layouts differ: dev writes to the "exports" bucket at the
     * root, production to the "coopcycle-data" bucket under "exports/". Taking
     * the prefix from --target, as the legacy export does, covers both.
     */
    protected const INCREMENTAL_PREFIX = 'v2';

    public function __construct(
        protected string $appName,
        protected MessageBusInterface $messageBus,
        protected EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
    }

    protected function addOptions(self $cmd): self
    {
        return $cmd
            // Incremental mode, see executeIncremental(). Opt-in for now: the
            // legacy day-range mode stays the default until every instance has
            // been migrated, so that deploying this code changes nothing until
            // the schedule is updated.
            ->addOption(
                'incremental', null,
                InputOption::VALUE_NONE,
                'Export everything modified since the watermark, as an immutable append-only file'
            )
            ->addOption(
                'full', null,
                InputOption::VALUE_NONE,
                'Incremental mode: ignore the watermark and export the whole history, one file per month'
            )
            ->addOption(
                'since', null,
                InputOption::VALUE_REQUIRED,
                'Incremental mode: override the start of the selection window (Y-m-d or Y-m-d H:i:s)'
            )
            ->addOption(
                'overlap-days', null,
                InputOption::VALUE_REQUIRED,
                'Incremental mode: also re-export rows modified in the last N days, on top of the watermark (0 disables)',
                30
            )
            ->addOption(
                'date-start', null,
                InputOption::VALUE_REQUIRED,
                'Legacy mode: start date',
                (new \DateTime())->modify('-1 day')->setTime(0, 0, 1)->format('Y-m-d')
            )
            ->addOption(
                'date-end', null,
                InputOption::VALUE_REQUIRED,
                'Legacy mode: end date',
                (new \DateTime())->modify('-1 day')->setTime(23, 59, 59)->format('Y-m-d')
            )
            ->addOption(
                'target', 't',
                InputOption::VALUE_REQUIRED,
                'Target directory'
            )
            // Credentials default to the environment so that callers do not have
            // to put them on the command line, where they leak into "ps" output
            // and into the command string this app logs when a command fails.
            ->addOption(
                's3-access-key', null,
                InputOption::VALUE_REQUIRED,
                'S3 access key (defaults to COOPCYCLE_S3_ACCESS_KEY)',
                $_SERVER['COOPCYCLE_S3_ACCESS_KEY'] ?? $_ENV['COOPCYCLE_S3_ACCESS_KEY'] ?? null
            )
            ->addOption(
                's3-secret-key', null,
                InputOption::VALUE_REQUIRED,
                'S3 secret key (defaults to COOPCYCLE_S3_SECRET_KEY)',
                $_SERVER['COOPCYCLE_S3_SECRET_KEY'] ?? $_ENV['COOPCYCLE_S3_SECRET_KEY'] ?? null
            )
            ->addOption(
                'format', 'f',
                InputOption::VALUE_REQUIRED,
                'Output format'
            )
            ->addOption('unsecure', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lockName = sprintf('%s_%s', $this->appName, $this->getName());
        if (!$this->lock($lockName)) {
            $output->writeln('The command is already running in another process.');
            return Command::FAILURE;
        }

        [$target, $options] = $this->parseTarget(
            $input->getOption('target'),
            $input->getOption('unsecure')
        );

        // Fail before doing any work rather than exporting every day in the
        // period and getting a 403 on each PutObject.
        if ('s3' === $target
            && (empty($input->getOption('s3-access-key')) || empty($input->getOption('s3-secret-key')))) {
            throw new \InvalidArgumentException(
                'Missing S3 credentials: pass --s3-access-key/--s3-secret-key or set COOPCYCLE_S3_ACCESS_KEY/COOPCYCLE_S3_SECRET_KEY.'
            );
        }

        // TODO Validate target & format here

        if ($input->getOption('incremental')) {
            return $this->executeIncremental($input, $output, $target, $options);
        }

        return $this->executeLegacy($input, $output, $target, $options);
    }

    /**
     * Watermark driven, append-only export.
     *
     * Every run writes new immutable files; nothing is ever rewritten, which is
     * what lets ClickHouse's S3Queue consume each file exactly once, and lets a
     * wipe and rebuild replay the whole bucket in any order.
     *
     * @param array<mixed> $options
     */
    private function executeIncremental(
        InputInterface $input,
        OutputInterface $output,
        string $target,
        array $options
    ): int
    {
        $io = new SymfonyStyle($input, $output);

        $watermark = $this->getWatermark();

        // The snapshot is taken before any row is read, and rows are selected
        // strictly below it. Anything modified while the export runs therefore
        // falls into the *next* run rather than into the gap between the two.
        //
        // It comes from the database clock, not from PHP's: the watermark is
        // compared against updated_at, and PHP runs in the instance's local
        // timezone while Postgres stores UTC. Taking it from PHP stored a
        // watermark hours ahead of the rows it selects, so everything modified
        // in between was skipped -- silently, and by an amount that changes
        // with the season and the deployment.
        $snapshot = $this->getDatabaseTime();

        [$since, $watermarkAt] = $this->getSelectionWindow($input, $watermark, $snapshot);

        $runId = $snapshot->format('Y-m-d\TH-i-s');

        $rows = 0;
        $overlapRows = 0;

        foreach ($this->getChunks($since, $snapshot) as $index => $chunk) {

            [$chunkStart, $chunkEnd] = $chunk;

            $csv = $this->exportModifiedBetween($chunkStart, $chunkEnd);

            if (empty($csv)) {
                continue;
            }

            $this->assertWellFormedCsv($csv);

            [$chunkRows, $chunkOverlapRows] = $this->countRows($csv, $watermarkAt);
            $rows += $chunkRows;
            $overlapRows += $chunkOverlapRows;

            $contents = $this->csv2parquet($csv, $snapshot);

            $path = $this->getIncrementalPath(
                $options['key'] ?? '',
                $snapshot,
                $index > 0 ? sprintf('%s-%03d', $runId, $index) : $runId,
                $input->getOption('format')
            );

            switch ($target) {
                case 's3':
                    $this->pushToS3(
                        $path,
                        $contents,
                        $options,
                        $input->getOption('s3-access-key'),
                        $input->getOption('s3-secret-key')
                    );
                    break;

                case 'file':
                    $file = rtrim($options['path'], '/') . '/' . $path;
                    if (!is_dir(dirname($file))) {
                        mkdir(dirname($file), 0777, true);
                    }
                    file_put_contents($file, $contents);
                    break;
            }

            $io->writeln(sprintf('Wrote %d rows to %s', $chunkRows, $path));
        }

        // Only now that every file has landed do we move the watermark, so a
        // failure part way through simply replays the same window next time.
        $watermark
            ->setWatermarkAt($snapshot)
            ->setLastRunAt($snapshot)
            ->setLastRunRows($rows)
            ->setLastRunOverlapRows($overlapRows);

        $this->entityManager->flush();

        $io->success(sprintf(
            '%d rows exported (%d of them only because of the overlap window)',
            $rows,
            $overlapRows
        ));

        return Command::SUCCESS;
    }

    /**
     * The pre-existing day-per-file export, kept until every instance has moved
     * to --incremental and the schedule has been updated.
     *
     * @param array<mixed> $options
     */
    private function executeLegacy(
        InputInterface $input,
        OutputInterface $output,
        string $target,
        array $options
    ): int
    {
        foreach ($this->getDatePeriod($input) as $date) {

            $export = $this->exportData(clone $date, clone $date);

            if (empty($export)) {
                continue;
            }

            switch ($input->getOption('format')) {
                case 'parquet':
                    $export = $this->csv2parquet($export);
                    break;
            }

            switch ($target) {
                case 's3':

                    $path = sprintf('%s/%s', $options['key'], $this->getHivePartitioningPath($date, $input->getOption('format')));

                    $this->pushToS3(
                        $path,
                        $export,
                        $options,
                        $input->getOption('s3-access-key'),
                        $input->getOption('s3-secret-key')
                    );
                    break;

                case 'file':
                    file_put_contents($options['path'], $export);
                    break;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * The current time according to the database, so that the watermark and
     * the timestamps it is compared against share a single clock.
     */
    private function getDatabaseTime(): \DateTime
    {
        return new \DateTime(
            $this->entityManager->getConnection()->executeQuery('SELECT NOW()')->fetchOne()
        );
    }

    private function getWatermark(): ExportWatermark
    {
        $repository = $this->entityManager->getRepository(ExportWatermark::class);

        $watermark = $repository->find($this->getDatasetName());

        if (null === $watermark) {
            $watermark = new ExportWatermark($this->getDatasetName());
            $this->entityManager->persist($watermark);
        }

        return $watermark;
    }

    /**
     * Returns the start of the selection window, and the watermark it was
     * derived from (null when exporting the whole history).
     *
     * The window starts at the earlier of the watermark and "N days ago": the
     * watermark alone is correct for anything that bumps updated_at, and the
     * overlap catches changes that do not, such as an order total moving
     * because a refund was recorded against it.
     *
     * @return array{0: ?\DateTimeInterface, 1: ?\DateTimeInterface}
     */
    private function getSelectionWindow(
        InputInterface $input,
        ExportWatermark $watermark,
        \DateTimeInterface $snapshot
    ): array
    {
        if ($input->getOption('full')) {
            return [null, null];
        }

        if (null !== $since = $input->getOption('since')) {
            return [new \DateTime($since), null];
        }

        $watermarkAt = $watermark->getWatermarkAt();

        if (null === $watermarkAt) {
            // Never exported: the first run is a full export, no special case.
            return [null, null];
        }

        $overlapDays = intval($input->getOption('overlap-days'));

        if ($overlapDays <= 0) {
            return [$watermarkAt, $watermarkAt];
        }

        $overlapFrom = (clone $snapshot)->modify(sprintf('-%d days', $overlapDays));

        // Compared as the database would compare them: Doctrine hydrates the
        // stored watermark into PHP's timezone, so comparing the two objects
        // as instants would shift the window by the UTC offset.
        $since = $watermarkAt->format('Y-m-d H:i:s') < $overlapFrom->format('Y-m-d H:i:s')
            ? $watermarkAt
            : $overlapFrom;

        return [$since, $watermarkAt];
    }

    /**
     * A normal run is a single chunk. A full export is split by month, because
     * both the CSV and the Parquet file are built in memory and a single file
     * holding an instance's entire history would not fit.
     *
     * @return array<int, array{0: ?\DateTimeInterface, 1: \DateTimeInterface}>
     */
    private function getChunks(?\DateTimeInterface $since, \DateTimeInterface $until): array
    {
        if (null !== $since) {
            return [[$since, $until]];
        }

        $earliest = $this->getEarliestModifiedAt();

        if (null === $earliest) {
            return [];
        }

        $chunks = [];
        $start = (new \DateTime($earliest->format('Y-m-01')))->setTime(0, 0, 0);

        while ($start < $until) {
            $end = (clone $start)->modify('+1 month');
            $chunks[] = [clone $start, min($end, $until)];
            $start = $end;
        }

        return $chunks;
    }

    /**
     * Fail on a record whose field count does not match the header.
     *
     * The export round-trips through CSV, and free text written by couriers and
     * dispatchers goes through it: comments and notes routinely contain
     * newlines and quotes. When a value breaks the quoting, the record is split
     * and League\Csv pads the short half with nulls, which surfaces much later
     * as a type error on whichever column happens to be read first -- naming
     * neither the row nor the reason. Check it here, where the offending record
     * can still be pointed at.
     */
    private function assertWellFormedCsv(string $csv): void
    {
        // Read exactly as the writer wrote: the escape character has to match
        // on both sides. Setting it to '' here while the writer keeps PHP's
        // default silently splits any field containing a backslash before a
        // quote -- which is how a Hamburg address named
        // 111; BILD hilft e.V. \"Ein Herz fur Kinder\" , turned one record
        // into two.
        $reader = Reader::createFromString($csv);

        $expected = null;

        foreach ($reader->getRecords() as $offset => $record) {

            if (null === $expected) {
                $expected = count($record);
                continue;
            }

            if (count($record) === $expected) {
                continue;
            }

            throw new \RuntimeException(sprintf(
                'Malformed CSV at record %d: %d fields instead of %d. A value has '
                    . 'most likely broken the quoting. First fields: %s',
                $offset,
                count($record),
                $expected,
                implode(' | ', array_map(
                    fn ($value): string => substr((string) $value, 0, 40),
                    array_slice($record, 0, 5)
                ))
            ));
        }
    }

    /**
     * @return array{0: int, 1: int} total rows, and rows that only the overlap
     *                               window selected
     */
    private function countRows(string $csv, ?\DateTimeInterface $watermarkAt): array
    {
        $reader = Reader::createFromString($csv);
        $reader->setHeaderOffset(0);

        $total = count($reader);

        if (null === $watermarkAt) {
            return [$total, 0];
        }

        $overlap = 0;
        foreach ($reader as $record) {
            // The two exports name the column differently, tasks being
            // camelCase and orders snake_case.
            $updatedAt = $record['updatedAt'] ?? $record['updated_at'] ?? null;
            if (empty($updatedAt)) {
                continue;
            }
            if (new \DateTime($updatedAt) < $watermarkAt) {
                $overlap++;
            }
        }

        return [$total, $overlap];
    }

    /**
     * @return array<mixed>
     */
    protected function parseTarget(string $target, bool $unsecure = false): array
    {
        $parsed = parse_url($target);
        if (!$parsed) {
            throw new \Exception('Invalid target');
        }
        switch (strtolower($parsed['scheme'])) {
            case 's3':
                $_path = explode('/', ltrim($parsed['path'], '/'));
                $parsed['bucket'] = $_path[0];
                unset($_path[0]);
                $parsed['path'] = implode('/', $_path);
                return [
                    's3',
                    [
                        'endpoint' => sprintf('%s://%s',
                            $unsecure ? 'http' : 'https',
                            implode(':', array_filter(
                                [$parsed['host'], $parsed['port'] ?? null]
                            ))),
                        'bucket' => $parsed['bucket'],
                        'key' => $parsed['path']
                    ]
                ];
            case 'file':
                return [
                    'file', [ 'path' => $parsed['path'] ]
                ];
            default:
                throw new \Exception('Unsupported scheme');
        }
    }

    /**
     * @param mixed $date
     */
    protected function parseDate($date): \DateTime
    {
        if ($date instanceof \DateTime) {
            return $date;
        }

        return \DateTime::createFromFormat('Y-m-d', $date);
    }

    /**
     * @param array{endpoint: string, bucket: string, key: string} $options
     */
    protected function pushToS3(
        string $location,
        string $contents,
        array $options,
        string $accessKey,
        string $secretKey,
        bool   $pathStyle = true
    ): void {
        $client = new S3Client([
            'endpoint' => $options['endpoint'],
            'use_path_style_endpoint' => $pathStyle,
            'region' => 'fr-fr',
            'credentials' => [
                'key' => $accessKey,
                'secret' => $secretKey
            ]
        ]);

        $adapter = new AwsS3V3Adapter($client, $options['bucket']);
        $filesystem = new Filesystem($adapter);
        $filesystem->write($location, $contents);
    }

    protected function getDatePeriod(InputInterface $input): \DatePeriod
    {
        return new \DatePeriod(
            $this->parseDate($input->getOption('date-start')),
            \DateInterval::createFromDateString('1 day'),
            $this->parseDate($input->getOption('date-end')),
            \DatePeriod::INCLUDE_END_DATE
        );
    }

    protected function getHivePartitioningPath(\DateTimeInterface $date, string $format): string
    {
        return sprintf('year=%s/month=%s/%s.%s',
            $date->format('Y'), $date->format('m'), $date->format('Y-m-d'), $format);
    }

    /**
     * The instance owns its own prefix, so it can be given credentials scoped
     * to that prefix and nothing it writes can collide with another instance.
     *
     * Files are partitioned by *export* time, not by the day the rows belong
     * to: that is what makes them immutable, and it is why a task completed
     * days late no longer requires an old file to be rewritten.
     */
    protected function getIncrementalPath(
        string $key,
        \DateTimeInterface $exportedAt,
        string $runId,
        string $format
    ): string
    {
        return implode('/', array_filter([
            trim($key, '/'),
            self::INCREMENTAL_PREFIX,
            $this->getDatasetName(),
            sprintf('instance=%s', $this->appName),
            sprintf('exported=%s', $exportedAt->format('Y-m')),
            sprintf('%s.%s', $runId, $format),
        ]));
    }

    /**
     * Name of the dataset, used both as the watermark key and as the S3 prefix.
     */
    abstract protected function getDatasetName(): string;

    /**
     * Oldest modification date in this instance's database, used to bound a
     * full export. Null when there is nothing to export at all.
     */
    abstract protected function getEarliestModifiedAt(): ?\DateTimeInterface;

    /**
     * Rows modified in [$since, $until), as CSV. A null $since means everything
     * up to $until.
     */
    abstract protected function exportModifiedBetween(?\DateTimeInterface $since, \DateTimeInterface $until): ?string;

    abstract protected function exportData(\DateTimeInterface $start, \DateTimeInterface $end): ?string;

    abstract protected function csv2parquet(string $csv, ?\DateTimeInterface $exportedAt = null): string;
}
