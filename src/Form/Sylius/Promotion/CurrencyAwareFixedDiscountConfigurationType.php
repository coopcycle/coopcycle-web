<?php

namespace AppBundle\Form\Sylius\Promotion;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Form\Model\Promotion as PromotionData;
use AppBundle\Form\Type\MoneyType;
use AppBundle\Sylius\Promotion\Action\FixedDiscountPromotionActionCommand;
use AppBundle\Sylius\Promotion\Action\PercentageDiscountPromotionActionCommand;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;

use Sylius\Bundle\PromotionBundle\Form\Type\Action\FixedDiscountConfigurationType;

class CurrencyAwareFixedDiscountConfigurationType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        // dd('configureOptions');

        $resolver->setDefault('currency', 'EUR');

        // $resolver->setDefaults([
        //     // 'entry_type' => UnitFixedDiscountConfigurationType::class,
        //     'entry_options' => fn (ChannelInterface $channel) => [
        //         // 'label' => $channel->getName(),
        //         'currency' => 'EUR',
        //     ],
        // ]);
    }

    public function getParent(): string
    {
        return FixedDiscountConfigurationType::class;
    }
}
