<?php

namespace AppBundle\Form\DataTransformer;

use AppBundle\Entity\Issue;
use Doctrine\ORM\EntityManagerInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class UsernameTransformer implements DataTransformerInterface
{
    private $userManager;

    public function __construct(UserManagerInterface $userManager)
    {
        $this->userManager = $userManager;
    }

    /**
     * {@inheritdoc}
     */
    public function transform($user)
    {
        if (null === $user) {
            return '';
        }

        return $user->getUsername();
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($username)
    {
        if (!$username) {
            return;
        }

        $user = $this->userManager->findUserByUsername($username);

        if (null === $user) {
            throw new TransformationFailedException(sprintf(
                'User with username "%s" does not exist',
                $username
            ));
        }

        return $user;
    }
}
