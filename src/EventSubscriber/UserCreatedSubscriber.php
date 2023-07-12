<?php

namespace AppBundle\EventSubscriber;

use Nucleos\UserBundle\Event\UserEvent;
use Nucleos\UserBundle\NucleosUserEvents;
use Nucleos\UserBundle\Model\UserManager as UserManagerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserCreatedSubscriber implements EventSubscriberInterface
{
    private $channelContext;
    private $userManager;

    public function __construct(
        ChannelContextInterface $channelContext,
        UserManagerInterface $userManager)
    {
        $this->channelContext = $channelContext;
        $this->userManager = $userManager;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            NucleosUserEvents::USER_CREATED => 'setChannel',
        );
    }

    public function setChannel(UserEvent $event)
    {
        $user = $event->getUser();

        try {
            $user->setChannel($this->channelContext->getChannel());
            $this->userManager->updateUser($user);
        } catch (ChannelNotFoundException $e) {

        }
    }
}
