<?php

namespace AppBundle\Command;

use AppBundle\Entity\Marketing\EmailSuppression;
use AppBundle\Service\Marketing\EmailSuppressionManager;
use AppBundle\Service\Marketing\MarketingAutomationStatus;
use AppBundle\Service\Marketing\PostmarkClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

/**
 * Pulls Postmark's suppression list for a stream into the platform's copy.
 *
 * Webhooks only report what happens once they're wired up, so this is what
 * an instance that has been sending for a while needs in order to not start
 * out believing nobody ever unsubscribed. Safe to run repeatedly -- worth
 * doing on a schedule as a backstop for webhooks Postmark couldn't deliver.
 */
final class SyncPostmarkSuppressionsCommand extends Command
{
    protected static $defaultName = 'coopcycle:marketing:sync-suppressions';

    public function __construct(
        private readonly MarketingAutomationStatus $marketingAutomationStatus,
        private readonly PostmarkClient $postmarkClient,
        private readonly EmailSuppressionManager $suppressionManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription("Pulls Postmark's suppression list into the platform")
            ->addOption('stream', null, InputOption::VALUE_REQUIRED, 'Message stream to sync (defaults to the configured broadcast stream)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be recorded, without writing anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->marketingAutomationStatus->isEnvEnabled()) {
            $output->writeln('<comment>Marketing automation is not enabled on this instance.</comment>');

            return Command::SUCCESS;
        }

        if (!$this->postmarkClient->isConfigured()) {
            $output->writeln('<error>No Postmark server token is configured.</error>');

            return Command::FAILURE;
        }

        $stream = $input->getOption('stream') ?: $this->postmarkClient->getBroadcastStream();
        $dryRun = $input->getOption('dry-run');

        try {
            $suppressions = $this->postmarkClient->getSuppressions($stream);
        } catch (ExceptionInterface $e) {
            // A wrong token or a stream that doesn't exist is a configuration
            // mistake, not a bug -- say so plainly rather than handing the
            // admin a stack trace.
            $output->writeln(sprintf(
                '<error>Could not read the suppression list for stream "%s" from Postmark: %s</error>',
                $stream,
                $e->getMessage()
            ));

            return Command::FAILURE;
        }

        $count = 0;

        foreach ($suppressions as $suppression) {
            $email = $suppression['EmailAddress'] ?? null;

            if (empty($email)) {
                continue;
            }

            $count++;

            if ($dryRun) {
                continue;
            }

            $this->suppressionManager->suppress(
                $email,
                $stream,
                self::reasonFor($suppression['SuppressionReason'] ?? null, $suppression['Origin'] ?? null),
                self::parseDate($suppression['CreatedAt'] ?? null)
            );
        }

        $output->writeln(sprintf(
            '<info>%s%d suppression(s) on stream "%s" %s.</info>',
            $dryRun ? '[dry-run] ' : '',
            $count,
            $stream,
            $dryRun ? 'would be recorded' : 'recorded'
        ));

        return Command::SUCCESS;
    }

    /**
     * Mirrors PostmarkSuppressionEventHandler: the dump API reports the same
     * reasons as the webhook, and tells an unsubscribe from an admin-side
     * suppression by Origin in exactly the same way.
     */
    private static function reasonFor(?string $suppressionReason, ?string $origin): string
    {
        return match ($suppressionReason) {
            'HardBounce' => EmailSuppression::REASON_HARD_BOUNCE,
            'SpamComplaint' => EmailSuppression::REASON_SPAM_COMPLAINT,
            'ManualSuppression' => 'Recipient' === $origin
                ? EmailSuppression::REASON_UNSUBSCRIBE
                : EmailSuppression::REASON_MANUAL,
            default => EmailSuppression::REASON_MANUAL,
        };
    }

    private static function parseDate(?string $date): ?\DateTime
    {
        if (empty($date)) {
            return null;
        }

        try {
            return new \DateTime($date);
        } catch (\Exception $e) {
            return null;
        }
    }
}
