<?php

namespace AppBundle\Utils;

class MessageLoggingMailer extends \Swift_Mailer
{
	private $messageLogger;

    public function __construct()
    {
    	$eventDispatcher = new \Swift_Events_SimpleEventDispatcher();
    	$transport = new \Swift_Transport_NullTransport($eventDispatcher);

    	parent::__construct($transport);

    	$this->messageLogger = new \Swift_Plugins_MessageLogger();

    	$this->registerPlugin($this->messageLogger);
    }

    public function getMessages()
    {
        return $this->messageLogger->getMessages();
    }
}
