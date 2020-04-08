<?php

namespace AppBundle\Utils;

use AppBundle\Entity\TimeSlot;
use AppBundle\Form\Type\TimeSlotChoiceLoader;
use AppBundle\Sylius\Order\OrderInterface;
// use AppBundle\Utils\OpeningHoursSpecification;
use AppBundle\Utils\PreparationTimeCalculator;
use AppBundle\Utils\ShippingDateFilter;
use AppBundle\Utils\ShippingTimeCalculator;
use Carbon\Carbon;

class OrderTimeHelper
{
    private $shippingDateFilter;
    private $preparationTimeCalculator;
    private $shippingTimeCalculator;
    private $choicesCache = [];

    public function __construct(
        ShippingDateFilter $shippingDateFilter,
        PreparationTimeCalculator $preparationTimeCalculator,
        ShippingTimeCalculator $shippingTimeCalculator)
    {
        $this->shippingDateFilter = $shippingDateFilter;
        $this->preparationTimeCalculator = $preparationTimeCalculator;
        $this->shippingTimeCalculator = $shippingTimeCalculator;
    }

    private function filterChoices(OrderInterface $cart, array $choices)
    {
        return array_filter($choices, function ($date) use ($cart) {
            return $this->shippingDateFilter->accept($cart, new \DateTime($date));
        });
    }

    public function getAvailabilities(OrderInterface $cart)
    {
        $restaurant = $cart->getRestaurant();
        $hash = spl_object_hash($cart);

        // if ($restaurant->getOpeningHoursBehavior() === 'time_slot') {
        if (true) {
            $timeSlot = new TimeSlot();
            $timeSlot->setOpeningHours($restaurant->getOpeningHours());

            $choiceLoader = new TimeSlotChoiceLoader($timeSlot, 'fr');
            $choiceList = $choiceLoader->loadChoiceList();

            var_dump(count($choiceList->getChoices()));


            foreach ($choiceList->getChoices() as $choice) {
                var_dump((string) $choice);
                # code...
            }

            // $openingHoursSpecification =
            //     OpeningHoursSpecification::fromOpeningHours($restaurant->getOpeningHours());

        }

        if (!isset($this->choicesCache[$hash])) {

            $availabilities = $this->filterChoices($cart, $restaurant->getAvailabilities());

            if (empty($availabilities) && 1 === $restaurant->getShippingOptionsDays()) {
                $restaurant->setShippingOptionsDays(2);
                $availabilities = $this->filterChoices($cart, $restaurant->getAvailabilities());
                $restaurant->setShippingOptionsDays(1);
            }

            // Make sure to return a zero-indexed array
            $this->choicesCache[$hash] = array_values($availabilities);
        }

        return $this->choicesCache[$hash];
    }

    public function getTimeInfo(OrderInterface $cart)
    {
        $restaurant = $cart->getRestaurant();

        $preparationTime = $this->preparationTimeCalculator
            ->createForRestaurant($restaurant)
            ->calculate($cart);

        $shippingTime = $this->shippingTimeCalculator->calculate($cart);

        $asap = $this->getAsap($cart);

        if (null !== $cart->getShippedAt()) {
            $today = $cart->getShippedAt()->format('Y-m-d') === Carbon::now()->format('Y-m-d');
        } else {
            $today = (new \DateTime($asap))->format('Y-m-d') === Carbon::now()->format('Y-m-d');
        }

        $diffInMinutes = Carbon::now()->diffInMinutes(Carbon::parse($asap));

        // We consider it is "fast" if it's less than 45 minutes
        $fast = $diffInMinutes < 45;

        // Round the diff to be a multiple of 5
        if (($diffInMinutes % 5) !== 0) {
            do {
                ++$diffInMinutes;
            } while (($diffInMinutes % 5) !== 0);
        }

        return [
            'behavior' => 'time_slot', /* null === $restaurant->getTimeSlot() ? 'asap' : 'time_slot'*/
            'preparation' => $preparationTime,
            'shipping' => $shippingTime,
            'asap' => $asap,
            'today' => $today,
            'fast' => $fast,
            'diff' => sprintf('%d - %d', $diffInMinutes, ($diffInMinutes + 5)),
        ];
    }

    public function getAsap(OrderInterface $cart)
    {
        $choices = $this->getAvailabilities($cart);

        // TODO Use sort
        return $choices[0];
    }
}
