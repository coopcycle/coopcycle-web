<?php
declare(strict_types=1);

namespace AppBundle\Sylius\Channel;

use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ProChannelContext implements ChannelContextInterface
{
    /**
     * @var ChannelRepositoryInterface
     */
    private ChannelRepositoryInterface $channelRepository;
    /**
     * @var Request|null
     */
    private ?Request $masterRequest;
    private const KEY_CHANNEL_CART = 'channel_cart';

    public function __construct(ChannelRepositoryInterface $channelRepository, RequestStack $request)
    {

        $this->channelRepository = $channelRepository;
        $this->masterRequest = $request->getMasterRequest();
    }

    public function getChannel(): ChannelInterface
    {
        $request = $this->getRequest();
        $channelCode = $request->query->get('change_channel', $request->get(self::KEY_CHANNEL_CART, 'web'));
        if (!in_array($channelCode, ['web', 'pro'])) {
            throw new ChannelNotFoundException();
        }
        $channel = $this->channelRepository->findOneByCode($channelCode);
        if (null === $channel) {
            throw new ChannelNotFoundException();
        }

        return $channel;

    }

    private function getRequest(): Request
    {
        if (null === $this->masterRequest) {
            throw new ChannelNotFoundException();
        }

        return $this->masterRequest;
    }
}
