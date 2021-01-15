<?php

namespace AppBundle\Domain;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Translation\TranslatorInterface;

interface HumanReadableEventInterface
{
    public function forHumans(TranslatorInterface $translator, UserInterface $user = null);
}
