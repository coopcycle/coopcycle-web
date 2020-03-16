<?php

namespace AppBundle\Entity;

use AppBundle\Entity\Restaurant;
use AppBundle\Utils\RestaurantFilter;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Order\Model\OrderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ShopRepository extends RestaurantRepository
{

}
