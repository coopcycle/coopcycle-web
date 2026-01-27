<?php

namespace AppBundle\Security;

use AppBundle\Sylius\Product\ProductInterface;
use AppBundle\Entity\Sylius\Taxon;
use AppBundle\Entity\Sylius\TaxonRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Webmozart\Assert\Assert;

class RestaurantMenuVoter extends Voter
{
    const EDIT = 'edit';

    private static $actions = [
        self::EDIT,
    ];


    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private TaxonRepository $taxonRepository)
    {
    }

    protected function supports($attribute, $subject): bool
    {
        if (!in_array($attribute, self::$actions)) {
            return false;
        }

        if (!$subject instanceof Taxon) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        if (!is_object($user = $token->getUser())) {
            return false;
        }

        if ($this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            return true;
        }

        if (!$this->authorizationChecker->isGranted('ROLE_RESTAURANT')) {
            return false;
        }

        $taxon = $subject->isRoot() ? $subject : $subject->getRoot();
        $restaurant = $this->taxonRepository->getRestaurantForMenu($taxon);

        return $this->authorizationChecker->isGranted('edit', $restaurant);
    }
}
