<?php

namespace AppBundle\Command;

use AppBundle\Service\Marketing\MarketingAutomationStatus;
use AppBundle\Service\Marketing\MarketingMailer;
use AppBundle\Service\Marketing\MarketingMailerNotConfiguredException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;

/**
 * Sends one message through the broadcast stream, to check an instance's
 * Postmark setup end to end.
 *
 * Marketing mail has more ways to be misconfigured than transactional does
 * -- an unverified sender signature, a transactional stream used by mistake,
 * a token for the wrong server -- and all of them fail at Postmark rather
 * than here. This makes an admin find out now instead of at the moment they
 * send a campaign to everyone.
 */
final class SendTestMarketingEmailCommand extends Command
{
    protected static $defaultName = 'coopcycle:marketing:send-test-email';

    public function __construct(
        private readonly MarketingAutomationStatus $marketingAutomationStatus,
        private readonly MarketingMailer $marketingMailer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Sends a test email through the Postmark broadcast stream')
            ->addArgument('recipient', InputArgument::REQUIRED, 'Address to send the test to');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->marketingAutomationStatus->isEnvEnabled()) {
            $output->writeln('<comment>Marketing automation is not enabled on this instance.</comment>');

            return Command::SUCCESS;
        }

        $recipient = $input->getArgument('recipient');

        if (false === filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $output->writeln(sprintf('<error>"%s" is not a valid email address.</error>', $recipient));

            return Command::FAILURE;
        }

        $email = (new Email())
            ->to($recipient)
            ->subject('Test message')
            ->text(
                "This is a test message sent through the Postmark broadcast stream.\n\n"
                . "If you received it, campaigns are configured correctly."
            );

        try {
            $sent = $this->marketingMailer->send($email);
        } catch (MarketingMailerNotConfiguredException $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

            return Command::FAILURE;
        } catch (TransportExceptionInterface $e) {
            // Postmark rejects an unverified sender or an unknown stream here
            // rather than at configuration time, so its message is the useful
            // part to surface.
            $output->writeln(sprintf('<error>Postmark refused the message: %s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        if (!$sent) {
            $output->writeln(sprintf(
                '<comment>Nothing sent: "%s" is on the suppression list.</comment>',
                $recipient
            ));

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>Test message sent to %s.</info>', $recipient));

        return Command::SUCCESS;
    }
}
