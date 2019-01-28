<?php

namespace AppBundle\Sylius\Channel;

use Sylius\Component\Channel\Context\ChannelContextInterface;
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
        return $this->channelRepository->findOneByCode('web');
    }
}
