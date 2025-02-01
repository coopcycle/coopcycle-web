<?php

namespace AppBundle\Messenger;

use AppBundle\Log\MessengerRequestIdProcessor;
use AppBundle\Messenger\Stamp\RequestIdStamp;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\Stamp\StampInterface;

class RequestIdMiddleware extends StampMiddleware
{
    public function __construct(
        private readonly RequestStack $requestStack,
        MessengerRequestIdProcessor $stampProcessor,
    )
    {
        parent::__construct($stampProcessor);
    }

    protected function getStampFqcn(): string
    {
        return RequestIdStamp::class;
    }

    protected function createStamp(): ?StampInterface
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request && $request->headers->has('X-Request-ID')) {
            return new RequestIdStamp($request->headers->get('X-Request-ID'));
        } else {
            return null;
        }
    }
}
