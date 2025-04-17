<?php

namespace AppBundle\Security;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Api\UrlGeneratorInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Exception\ItemNotFoundException;
use AppBundle\Sylius\Order\OrderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\ExpiredTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\InvalidTokenException;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTDecodeFailureException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class OrderAccessTokenManager
{
    function __construct(
        private JWTEncoderInterface $jwtEncoder,
        private JWTTokenManagerInterface $jwtManager,
        private IriConverterInterface $iriConverter)
    {
    }

    public function create($order): string
    {
        $payload = [
            'sub' => $this->iriConverter->getIriFromResource($order, UrlGeneratorInterface::ABS_URL),
            'exp' => time() + (60 * 60 * 24),
        ];

        return $this->jwtEncoder->encode($payload);
    }

    public function parse($rawToken): OrderInterface|false
    {
        try {
            if (!$payload = $this->jwtManager->parse($rawToken)) {
                throw new InvalidTokenException('Invalid JWT Token');
            }
        } catch (JWTDecodeFailureException $e) {
            if (JWTDecodeFailureException::EXPIRED_TOKEN === $e->getReason()) {
                throw new ExpiredTokenException();
            }

            throw new InvalidTokenException('Invalid JWT Token', 0, $e);
        }

        if (isset($payload['sub'])) {
            try {
                return $this->iriConverter->getResourceFromIri($payload['sub']);
            } catch (InvalidArgumentException|ItemNotFoundException $e) {
            }
        }

        return false;
    }
}
