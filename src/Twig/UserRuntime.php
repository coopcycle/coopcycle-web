<?php

namespace AppBundle\Twig;

use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Twig\Extension\RuntimeExtensionInterface;

class UserRuntime implements RuntimeExtensionInterface
{
    public function __construct(private Security $security, private NormalizerInterface $normalizer)
    {}

    public function getUserAddresses()
    {
        $addresses = [];

        $user = $this->security->getUser();
        if ($user) {
            $addresses = $user->getAddresses()->toArray();
        }

        return array_map(function ($address) {

            return $this->normalizer->normalize($address, 'jsonld', [
                'groups' => ['address']
            ]);

        }, $addresses);
    }
}

