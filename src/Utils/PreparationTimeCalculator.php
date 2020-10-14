<?php

namespace AppBundle\Utils;

use AppBundle\Entity\LocalBusiness;
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
    private $cache = [];

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

    /**
     * Returns a time expression string, for ex. "15 minutes".
     *
     * @param OrderInterface $order
     * @return string
     */
    public function calculate(OrderInterface $order): string
    {
        $times = [];

        foreach ($order->getTarget()->toArray() as $restaurant) {

            $preparation = '0 minutes';
            foreach ($this->getConfig($restaurant) as $expression => $value) {

                $restaurantObject = new \stdClass();
                $restaurantObject->state = $restaurant->getState();

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

            $times[] = $preparation;
        }

        uasort($times, function ($a, $b) {
            $now = new \DateTime();
            $aDate = clone $now;
            $bDate = clone $now;

            $aDate->add(date_interval_create_from_date_string($a));
            $bDate->add(date_interval_create_from_date_string($b));

            return $aDate > $bDate ? -1 : 1;
        });

        return current($times);
    }

    private function getConfig(LocalBusiness $restaurant)
    {
        $oid = spl_object_hash($restaurant);

        if (!isset($this->cache[$oid])) {

            $rules = $restaurant->getPreparationTimeRules();
            $config = [];
            if (count($rules) > 0) {
                foreach ($rules as $rule) {
                    $config[$rule->getExpression()] = $rule->getTime();
                }
            }

            $this->cache[$oid] = $config;
        }

        return count($this->cache[$oid]) > 0 ? $this->cache[$oid] : $this->config;
    }
}
