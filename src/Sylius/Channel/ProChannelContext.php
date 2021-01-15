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
     * @var RequestStack
     */
    private RequestStack $requestStack;

    public const QUERY_PARAM_NAME = 'change_channel';

    public const COOKIE_KEY = 'channel_cart';

    public function __construct(ChannelRepositoryInterface $channelRepository, RequestStack $requestStack)
    {
        $this->channelRepository = $channelRepository;
        $this->requestStack = $requestStack;
    }

    public function getChannel(): ChannelInterface
    {
        $request = $this->getRequest();

        $channelCode = $request->query->get(self::QUERY_PARAM_NAME) ?: $request->cookies->get(self::COOKIE_KEY);

        if (null === $channelCode) {
            throw new ChannelNotFoundException();
        }

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
        $masterRequest = $this->requestStack->getMasterRequest();
        if (null === $masterRequest) {
            throw new ChannelNotFoundException();
        }

        return $masterRequest;
    }
}
