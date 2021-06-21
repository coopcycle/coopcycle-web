<?php

namespace AppBundle\Sylius\Channel;

use Sylius\Component\Channel\Context\ChannelNotFoundException;
use Sylius\Component\Channel\Context\RequestBased\RequestResolverInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;

final class ChannelResolver implements RequestResolverInterface
{
    const HEADER_NAME = 'x-coopcycle-channel';

    private $channelRepository;

    public function __construct(ChannelRepositoryInterface $channelRepository)
    {
        $this->channelRepository = $channelRepository;
    }

    public function findChannel(Request $request): ?ChannelInterface
    {
        if ($request->headers->has(self::HEADER_NAME)) {

            $code = $request->headers->get(self::HEADER_NAME);
            if ($channel = $this->channelRepository->findOneByCode($code)) {

                return $channel;
            }
        }

        $isApiRequest = $request->attributes->has('_api_resource_class');

        $code = $isApiRequest ? 'app' : 'web';

        if (!$channel = $this->channelRepository->findOneByCode($code)) {
            throw new ChannelNotFoundException();
        }

        return $channel;
    }
}
