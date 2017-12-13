<?php


namespace AppBundle\Service;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Swift_Events_SendEvent;
use Swift_Events_SendListener;


class MailLoggerService implements Swift_Events_SendListener
{
    protected $logger;

    /**
     * MailerLoggerUtil constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param Swift_Events_SendEvent $evt
     */
    public function beforeSendPerformed(Swift_Events_SendEvent $evt)
    {
    }

    /**
     * @param Swift_Events_SendEvent $evt
     */
    public function sendPerformed(Swift_Events_SendEvent $evt)
    {
        $level   = $this->getLogLevel($evt);
        $message = $evt->getMessage();

        $this->logger->log(
            $level,
            $message->getBody(),
            [
                'result'  => $evt->getResult(),
                'subject' => $message->getSubject(),
                'from'    => $message->getFrom(),
                'to'      => $message->getTo(),
                'cc'      => $message->getCc(),
                'bcc'     => $message->getBcc(),
            ]
        );
    }

    /**
     * @param Swift_Events_SendEvent $evt
     *
     * @return string
     */
    private function getLogLevel(Swift_Events_SendEvent $evt): string
    {
        switch ($evt->getResult()) {
            // Sending has yet to occur
            case Swift_Events_SendEvent::RESULT_PENDING:
                return LogLevel::DEBUG;

            // Email is spooled, ready to be sent
            case Swift_Events_SendEvent::RESULT_SPOOLED:
                return LogLevel::DEBUG;

            // Sending failed
            case Swift_Events_SendEvent::RESULT_FAILED:
                return LogLevel::CRITICAL;

            // Sending worked, but there were some failures
            case Swift_Events_SendEvent::RESULT_TENTATIVE:
                return LogLevel::ERROR;

            // Sending was successful
            case Swift_Events_SendEvent::RESULT_SUCCESS:
                return LogLevel::INFO;
            default:
                return LogLevel::INFO;
        }
    }
}