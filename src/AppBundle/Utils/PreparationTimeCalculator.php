<?php

namespace AppBundle\Utils;

use AppBundle\Entity\Restaurant;
use AppBundle\Sylius\Order\OrderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * The preparation duration is calculated based on the order total.
 * The $config constructor variable should be an array like this:
 * <pre>
 * [
 *   'total <= 2000'       => '10 minutes',
 *   'total in 2000..5000' => '15 minutes',
 *   'total > 5000'        => '30 minutes',
 * ]
 * </pre>
 */
class PreparationTimeCalculator
{
    private $config;
    private $language;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->language = new ExpressionLanguage();
    }

    public function getDefaultConfig()
    {
        return $this->config;
    }

    public function calculate(OrderInterface $order)
    {
        $preparation = '0 minutes';
        foreach ($this->config as $expression => $value) {

            $restaurantObject = new \stdClass();
            $restaurantObject->state = $order->getRestaurant()->getState();

            $orderObject = new \stdClass();
            $orderObject->itemsTotal = $order->getItemsTotal();

            $values = [
                'restaurant' => $restaurantObject,
                'order' => $orderObject,
            ];

            if (true === $this->language->evaluate($expression, $values)) {
                $preparation = $value;
                break;
            }
        }

        return $preparation;
    }

    public function createForRestaurant(Restaurant $restaurant)
    {
        $preparationTimeRules = $restaurant->getPreparationTimeRules();

        if (count($preparationTimeRules) > 0) {
            $config = [];

            foreach ($preparationTimeRules as $preparationTimeRule) {
                $config[$preparationTimeRule->getExpression()] = $preparationTimeRule->getTime();
            }

            return new self($config);
        }

        return $this;
    }
}
