<?php

declare(strict_types=1);

namespace AppBundle\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Guard\JWTTokenAuthenticator;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * @see https://github.com/lexik/LexikJWTAuthenticationBundle/issues/298
 */
class JWTAuthenticator extends JWTTokenAuthenticator
{
    /** @var FirewallMap */
    private $firewallMap;

    /** @var JWTTokenAuthenticator */
    private $decorated;

    public function __construct(
        JWTTokenAuthenticator $decorated,
        JWTTokenManagerInterface $jwtManager,
        EventDispatcherInterface $dispatcher,
        TokenExtractorInterface $tokenExtractor,
        TokenStorageInterface $preAuthenticationTokenStorage)
    {
        $this->decorated = $decorated;

        parent::__construct($jwtManager, $dispatcher, $tokenExtractor, $preAuthenticationTokenStorage);
    }

    /**
     * @param FirewallMap $firewallMap
     */
    public function setFirewallMap(FirewallMap $firewallMap): void
    {
        $this->firewallMap = $firewallMap;
    }

    /**
     * @param Request $request
     *
     * @return bool
     */
    public function supports(Request $request): bool
    {
        try {
            return $this->decorated->supports($request) && $this->decorated->getCredentials($request);
        } catch (AuthenticationException $e) {
            if ($this->firewallMap->getFirewallConfig($request)->allowsAnonymous()) {
                return false;
            }

            throw $e;
        }
    }
}
