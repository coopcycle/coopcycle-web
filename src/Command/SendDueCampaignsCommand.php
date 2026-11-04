<?php

namespace AppBundle\Command;

use AppBundle\Entity\Marketing\CampaignRepository;
use AppBundle\Service\Marketing\CampaignNotSendableException;
use AppBundle\Service\Marketing\CampaignSender;
use AppBundle\Service\Marketing\MarketingAutomationStatus;
use AppBundle\Service\Marketing\MarketingMailerNotConfiguredException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Sends campaigns whose scheduled time has come.
 *
 * Meant to run on a cron, frequently enough that "scheduled for 9am" means
 * roughly 9am. Sending itself is queued, so this only ever does the
 * audience work and hands off.
 */
final class SendDueCampaignsCommand extends Command
{
    protected static $defaultName = 'coopcycle:marketing:send-due-campaigns';

    public function __construct(
        private readonly MarketingAutomationStatus $marketingAutomationStatus,
        private readonly CampaignRepository $campaignRepository,
        private readonly CampaignSender $campaignSender,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Sends scheduled campaigns that are due')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what is due, without sending anything');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // The runtime switch, not just the env var: an admin turning
        // campaigns off expects scheduled ones to stop going out.
        if (!$this->marketingAutomationStatus->isActive()) {
            $output->writeln('<comment>The marketing program is not active.</comment>');

            return Command::SUCCESS;
        }

        $dryRun = $input->getOption('dry-run');
        $due = $this->campaignRepository->findDue();

        if (empty($due)) {
            $output->writeln('<info>No campaigns are due.</info>');

            return Command::SUCCESS;
        }

        foreach ($due as $campaign) {
            if ($dryRun) {
                $output->writeln(sprintf(
                    '[dry-run] Campaign #%d "%s" (%s) is due.',
                    $campaign->getId(),
                    $campaign->getName(),
                    $campaign->getSegment()
                ));

                continue;
            }

            try {
                $audience = $this->campaignSender->send($campaign);
            } catch (CampaignNotSendableException | MarketingMailerNotConfiguredException $e) {
                // One badly configured campaign shouldn't stop the others
                // that are due alongside it.
                $output->writeln(sprintf('<error>Campaign #%d: %s</error>', $campaign->getId(), $e->getMessage()));

                continue;
            }

            $output->writeln(sprintf(
                '<info>Campaign #%d "%s" queued for %d recipient(s).</info>',
                $campaign->getId(),
                $campaign->getName(),
                $audience->count()
            ));
        }

        return Command::SUCCESS;
    }
}
