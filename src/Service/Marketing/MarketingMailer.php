<?php

namespace AppBundle\Service\Marketing;

use AppBundle\Entity\Marketing\EmailSuppressionRepository;
use AppBundle\Service\SettingsManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends on Postmark's broadcast stream, which is a different thing from the
 * transactional mail the rest of the platform sends.
 *
 * It gets its own transport rather than using the configured mailer for two
 * reasons: the credentials are per-tenant settings rather than an env var,
 * and marketing must go out on a *broadcast* stream -- that is what gives
 * every message an unsubscribe link and makes Postmark keep a suppression
 * list, which the whole consent story here rests on. Sending campaigns down
 * the transactional stream would quietly skip all of that.
 */
class MarketingMailer
{
    private ?TransportInterface $transport = null;
    private ?string $transportKey = null;

    public function __construct(
        private readonly SettingsManager $settingsManager,
        private readonly PostmarkClient $postmarkClient,
        private readonly EmailSuppressionRepository $suppressionRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->postmarkClient->isConfigured() && !empty($this->getSenderEmail());
    }

    public function getSenderAddress(): Address
    {
        $email = $this->getSenderEmail();

        if (empty($email)) {
            throw MarketingMailerNotConfiguredException::missing('the sender address');
        }

        $name = $this->settingsManager->get('postmark_sender_name')
            ?: (string) $this->settingsManager->get('brand_name');

        return new Address($email, (string) $name);
    }

    /**
     * Stamps the message with the sender and stream, then sends it.
     *
     * Refuses addresses the platform knows are suppressed. Postmark would
     * drop them anyway, so this isn't what keeps us compliant -- it's a
     * backstop in case an audience is ever built wrong, which is the one bug
     * in this feature that would actually harm someone.
     *
     * @return bool false when nothing was sent because every recipient is suppressed
     *
     * @throws MarketingMailerNotConfiguredException
     * @throws TransportExceptionInterface           Postmark rejects an unverified sender
     *                                               or an unknown stream at send time, not
     *                                               at configuration time
     */
    public function send(Email $email): bool
    {
        $recipients = [];

        foreach ($email->getTo() as $address) {
            if ($this->suppressionRepository->isSuppressed($address->getAddress())) {
                $this->logger->info(sprintf(
                    'Not sending marketing email to suppressed address "%s"',
                    $address->getAddress()
                ));

                continue;
            }

            $recipients[] = $address;
        }

        if (empty($recipients)) {
            return false;
        }

        $email->to(...$recipients);

        if ([] === $email->getFrom()) {
            $email->from($this->getSenderAddress());
        }

        $this->getTransport()->send($email);

        return true;
    }

    private function getSenderEmail(): ?string
    {
        $email = $this->settingsManager->get('postmark_sender_email');

        return empty($email) ? null : (string) $email;
    }

    /**
     * Rebuilt whenever the token or stream changes, since both are runtime
     * settings an admin can edit without a deploy.
     */
    private function getTransport(): TransportInterface
    {
        $token = (string) $this->settingsManager->get('postmark_server_token');

        if (empty($token)) {
            throw MarketingMailerNotConfiguredException::missing('the Postmark server token');
        }

        $stream = $this->postmarkClient->getBroadcastStream();
        $key = $token . '/' . $stream;

        if ($key !== $this->transportKey) {
            $this->transport = Transport::fromDsn(sprintf(
                'postmark+api://%s@default?message_stream=%s',
                urlencode($token),
                urlencode($stream)
            ));
            $this->transportKey = $key;
        }

        return $this->transport;
    }
}
