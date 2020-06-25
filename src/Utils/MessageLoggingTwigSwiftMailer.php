<?php

namespace AppBundle\Utils;

use FOS\UserBundle\Mailer\TwigSwiftMailer as BaseTwigSwiftMailer;

class MessageLoggingTwigSwiftMailer extends BaseTwigSwiftMailer
{
	public function getMessages()
    {
        return $this->mailer->getMessages();
    }
}
