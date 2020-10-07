<?php

namespace AppBundle\Security;

use AppBundle\Security\Exception\UserWithSameEmailExistsAuthenticationException;
use Sylius\Component\Order\Context\CartContextInterface;
use Doctrine\ORM\EntityManagerInterface;
use FOS\UserBundle\Util\CanonicalizerInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\User;

class GuestCheckoutAuthenticator extends AbstractGuardAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'guest_checkout';

    private $entityManager;
    private $urlGenerator;
    private $cartContext;
    private $customerFactory;
    private $canonicalizer;
    private $customerRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        UrlGeneratorInterface $urlGenerator,
        CartContextInterface $cartContext,
        FactoryInterface $customerFactory,
        CanonicalizerInterface $canonicalizer,
        RepositoryInterface $customerRepository)
    {
        $this->entityManager = $entityManager;
        $this->urlGenerator = $urlGenerator;
        $this->cartContext = $cartContext;
        $this->customerFactory = $customerFactory;
        $this->canonicalizer = $canonicalizer;
        $this->customerRepository = $customerRepository;
    }

    public function supports(Request $request)
    {
        return $request->isMethod('POST')
            && self::LOGIN_ROUTE === $request->attributes->get('_route');
    }

    public function getCredentials(Request $request)
    {
        $credentials = [
            'email' => $request->request->get('email'),
        ];

        $request->getSession()->set(
            Security::LAST_USERNAME,
            $credentials['email']
        );

        return $credentials;
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        return new User($credentials['email'], '');
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        $emailCanonical = $this->canonicalizer->canonicalize($credentials['email']);
        $customer = $this->customerRepository
            ->findOneBy([
                'emailCanonical' => $emailCanonical,
            ]);

        // If the email exists, and matches with a registered user, we force the user to login
        // This may not be the best practice, but it may cause other problems to allow this
        // @see https://security.stackexchange.com/questions/188509/what-are-the-security-implications-of-allowing-guest-checkout-using-an-email-bou
        if (null !== $customer && $customer->hasUser()) {
            throw new UserWithSameEmailExistsAuthenticationException(
                // TODO Translate error
                'User with same email exists. Please login.'
            );
        }

        if (null === $customer) {
            $customer = $this->customerFactory->createNew();
            $customer->setEmail($emailCanonical);
            $customer->setEmailCanonical($emailCanonical);
        }

        $cart = $this->cartContext->getCart();
        $cart->setCustomer($customer);

        $this->entityManager->flush();

        return true;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $providerKey)) {

            return new RedirectResponse($targetPath);
        }
    }

    /**
     * @return RedirectResponse
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        if ($request->hasSession()) {
            $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        }

        if ($exception instanceof UserWithSameEmailExistsAuthenticationException) {

            return new RedirectResponse(
                $this->urlGenerator->generate('fos_user_security_login')
            );
        }

        return new RedirectResponse(
            $this->urlGenerator->generate(self::LOGIN_ROUTE)
        );
    }

    public function supportsRememberMe()
    {
        return false;
    }

    /**
     * @return RedirectResponse
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        return new RedirectResponse(
            $this->urlGenerator->generate(self::LOGIN_ROUTE)
        );
    }
}
