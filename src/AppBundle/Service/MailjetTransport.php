<?php


namespace AppBundle\Service;


use Mailjet\Resources;
use Psr\Log\LoggerInterface;

class MailjetTransport extends \Mailjet\MailjetSwiftMailer\SwiftMailer\MailjetTransport
{
    public function __construct(\Swift_Events_EventDispatcher $eventDispatcher, $apiKey = null, $apiSecret = null, LoggerInterface $logger, $call = true, array $clientOptions = []) {
        $this->eventDispatcher = $eventDispatcher;
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->call = $call;
        $this->logger = $logger;
        $this->setClientOptions($clientOptions);
    }

    /**

     * @return int Number of messages sent
     */
    public function send(\Swift_Mime_Message $message, &$failedRecipients = null) {
        $this->resultApi = null;
        $failedRecipients = (array) $failedRecipients;
        if ($event = $this->eventDispatcher->createSendEvent($this, $message)) {
            $this->eventDispatcher->dispatchEvent($event, 'beforeSendPerformed');
            if ($event->bubbleCancelled()) {
                return 0;
            }
        }
        $sendCount = 0;

        // this the important part : a trailing '\n' in subject will break mailjet emails big time
        $message->setSubject(trim($message->getSubject()));

        // extract Mailjet Message from SwiftMailer Message
        $mailjetMessage = $this->messageFormat->getMailjetMessage($message);

        $this->logger->critical($message->getSubject());
        if (is_null($this->mailjetClient)) {
            // create Mailjet client
            $this->mailjetClient = $this->createMailjetClient();
        }


        try {
            // send API call
            $this->resultApi = $this->mailjetClient->post(Resources::$Email, ['body' => $mailjetMessage]);

            $sendCount = 1;
            // get result
            if ($this->resultApi->success()) {
                $resultStatus = \Swift_Events_SendEvent::RESULT_SUCCESS;
            } else {
                $resultStatus = \Swift_Events_SendEvent::RESULT_FAILED;
            }
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $failedRecipients = $message->getTo();
            $sendCount = 0;
            $resultStatus = \Swift_Events_SendEvent::RESULT_FAILED;
        }

        $this->logger->info(var_dump($this->resultApi->getBody()));

        // Send SwiftMailer Event
        if ($event) {
            $event->setResult($resultStatus);
            $event->setFailedRecipients($failedRecipients);
            $this->eventDispatcher->dispatchEvent($event, 'sendPerformed');
        }
        return $sendCount;
    }

}