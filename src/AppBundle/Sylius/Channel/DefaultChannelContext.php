<?php

namespace AppBundle\Sylius\Channel;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;

final class DefaultChannelContext implements ChannelContextInterface
{
    private $channelRepository;

    public function __construct(ChannelRepositoryInterface $channelRepository)
    {
        $this->channelRepository = $channelRepository;
    }

    public function getChannel(): ChannelInterface
    {
        if (!$channel = $this->channelRepository->findOneByCode('web')) {
            throw new ChannelNotFoundException();
        }

        return $channel;
    }
}
