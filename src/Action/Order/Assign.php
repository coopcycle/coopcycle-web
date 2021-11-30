<?php

namespace AppBundle\Action\Order;

use AppBundle\Service\OrderManager;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Nucleos\UserBundle\Util\CanonicalizerInterface;

class Assign
{
    public function __construct(
        TokenStorageInterface $tokenStorage,
        RepositoryInterface $customerRepository,
        CanonicalizerInterface $canonicalizer,
        FactoryInterface $customerFactory
        )
    {
        $this->tokenStorage = $tokenStorage;
        $this->customerRepository = $customerRepository;
        $this->canonicalizer = $canonicalizer;
        $this->customerFactory = $customerFactory;
    }

    public function __invoke($data, Request $request)
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            return $data;
        }

        if (!$token->hasAttribute('cart')) {

            return $data;
        }

        $cart = $token->getAttribute('cart');

        if ($cart && $data !== $cart) {
            throw new AccessDeniedException();
        }

        if (is_object($user = $token->getUser())) {
            $data->setCustomer($user->getCustomer());
        }

        $body = [];
        $content = $request->getContent();
        if (!empty($content)) {
            $body = json_decode($content, true);
        }

        if (isset($body['guest']) && true === $body['guest']) {
            if (!isset($body['email'])) {
                throw new BadRequestHttpException('Mandatory parameters are missing');
            }

            $customer = $this->findOrCreateCustomer($body['email'], $body['telephone']);

            $data->setCustomer($customer);
        }

        return $data;
    }

    private function findOrCreateCustomer($email, $telephone)
    {
        $customer = $this->customerRepository
            ->findOneBy([
                'emailCanonical' => $this->canonicalizer->canonicalize($email)
            ]);

        if (!$customer) {
            $customer = $this->customerFactory->createNew();

            $customer->setEmail($email);
            $customer->setEmailCanonical($this->canonicalizer->canonicalize($email));
        }

        $customer->setTelephone($telephone);

        return $customer;
    }

}
