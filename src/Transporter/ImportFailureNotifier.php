<?php

declare(strict_types=1);

namespace AppBundle\Transporter;

use AppBundle\Service\EmailManager;
use AppBundle\Service\SettingsManager;
use Psr\Log\LoggerInterface;

/**
 * Emails the cooperative's administrator (the `administrator_email` setting)
 * the files an import rejected, attached, used by `coopcycle:transporters:sync`
 * and `coopcycle:deliveries:import`. The CoopCycle developers are in CC.
 */
class ImportFailureNotifier
{
    const DEV_EMAIL = 'dev@coopcycle.org';

    public function __construct(
        private readonly EmailManager $emailManager,
        private readonly SettingsManager $settingsManager,
        private readonly LoggerInterface $transporterLogger,
    ) {
    }

    /**
     * @param string $source Who the files come from: a transporter, an importer
     * @param array<array-key,RejectedFile> $files
     */
    public function notify(string $source, array $files): void
    {
        if (empty($files)) {
            return;
        }

        // The import is done: a failure to notify must not fail it
        try {
            $message = $this->emailManager->createImportFailedMessage($source, $files);

            $administratorEmail = $this->settingsManager->get('administrator_email');
            if (empty($administratorEmail)) {
                $this->transporterLogger->warning(
                    sprintf('No administrator_email set, the rejected files of %s are only sent to %s', $source, self::DEV_EMAIL),
                    ['source' => $source]
                );
                $this->emailManager->sendTo($message, self::DEV_EMAIL);
                return;
            }

            $this->emailManager->sendTo($message->cc(self::DEV_EMAIL), $administratorEmail);
        } catch (\Throwable $e) {
            $this->transporterLogger->error(
                sprintf('Failed to notify the rejected files of %s: %s', $source, $e->getMessage()),
                ['source' => $source, 'exception' => $e]
            );
        }
    }
}
