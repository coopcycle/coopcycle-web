<?php

namespace AppBundle\Api\DataTransformer;

use ApiPlatform\Core\DataTransformer\DataTransformerInterface;
use ApiPlatform\Core\Serializer\AbstractItemNormalizer;
use AppBundle\Entity\Address;
use AppBundle\Entity\Store;
use Symfony\Component\HttpFoundation\RequestStack;

class StoreAddAddressDataTransformer implements DataTransformerInterface
{

    public function __construct(
        protected RequestStack $requestStack
    )
    {
        
    }
    /**
     * {@inheritdoc}
     */
    public function transform($object, string $to, array $context = [])
    {
        // we set the adress on the store here, because there is no proper way to do it in api platform 2.6
        // TODO  after migrating api platform to a new version, use a custom data processor to handle an adress as a subresource of a store
        // reference: https://github.com/api-platform/api-platform/issues/571#issuecomment-1473665701

        $store = $context[AbstractItemNormalizer::OBJECT_TO_POPULATE];
        $store->addAddress($object);

        return $store;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsTransformation($data, string $to, array $context = []): bool
    {
        if ($data instanceof Address) { // it means we are on normalization, don't do anything
          return false;
        }

        $route = $this->requestStack->getCurrentRequest()->attributes->get('_route');

        return Store::class === $to && $route === 'api_stores_add_address_item';
    }
}

