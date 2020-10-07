<?php

namespace AppBundle\Security\Exception;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class UserWithSameEmailExistsAuthenticationException extends CustomUserMessageAuthenticationException
{

}
