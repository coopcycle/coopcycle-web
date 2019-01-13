<?php

namespace AppBundle\Domain;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Translation\TranslatorInterface;

interface SerializableEventInterface
{
    public function normalize(SerializerInterface $serializer);

    public function forHumans(TranslatorInterface $translator, UserInterface $user = null);
}
