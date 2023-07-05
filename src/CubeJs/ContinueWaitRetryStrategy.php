<?php

namespace AppBundle\CubeJs;

use Symfony\Component\HttpClient\Retry\RetryStrategyInterface;
use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * https://cube.dev/docs/http-api/rest#prerequisites-continue-wait
 */
class ContinueWaitRetryStrategy implements RetryStrategyInterface
{
	public function shouldRetry(AsyncContext $context, ?string $responseContent, ?TransportExceptionInterface $exception): ?bool
	{
		if (null === $responseContent) {

			// Returning null means the body is required to take a decision
			return null;
		}

		if (200 === $context->getStatusCode()) {

			$data = json_decode($responseContent, true);

			if (isset($data['error']) && $data['error'] === 'Continue wait') {
				return true;
			}
		}

		return false;
	}

    public function getDelay(AsyncContext $context, ?string $responseContent, ?TransportExceptionInterface $exception): int
    {
    	return 3000;
    }
}
