<?php

namespace AppBundle\Form\Sylius\Promotion\EventSubscriber;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Sylius\Promotion\Checker\Rule\IsRestaurantRuleChecker;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class IsRestaurantRuleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LocalBusiness $restaurant,
        private FactoryInterface $promotionRuleFactory)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::POST_SET_DATA => 'removeRule',
            FormEvents::SUBMIT => 'addRule',
        ];
    }

    public function removeRule(FormEvent $event): void
    {
        $promotion = $event->getData();

        if (null === $promotion) {
            return;
        }

        $isRestaurantRule = $promotion->getRules()->filter(function ($rule) {
            return $rule->getType() === IsRestaurantRuleChecker::TYPE;
        })->first();

        if ($isRestaurantRule) {
            $promotion->getRules()->removeElement($isRestaurantRule);
        }
    }

    public function addRule(FormEvent $event): void
    {
        $promotion = $event->getData();

        $isRestaurantRule = $this->promotionRuleFactory->createNew();
        $isRestaurantRule->setType(IsRestaurantRuleChecker::TYPE);
        $isRestaurantRule->setConfiguration([
            'restaurant_id' => $this->restaurant->getId()
        ]);

        $promotion->addRule($isRestaurantRule);
    }
}

