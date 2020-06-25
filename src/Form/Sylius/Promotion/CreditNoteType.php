<?php

namespace AppBundle\Form\Sylius\Promotion;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Form\Type\MoneyType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints;

class CreditNoteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username', HiddenType::class)
            ->add('amount', MoneyType::class, [
                'label' => 'form.credit_note.amount.label'
            ])
            ->add('name', TextType::class, [
                'label' => 'form.credit_note.name.label',
                'help' => 'form.credit_note.name.help'
            ])
            ->add('restaurant', EntityType::class, [
                'class' => LocalBusiness::class,
                'choice_label' => 'name',
                'help' => 'form.credit_note.restaurant.help',
                'required' => false,
            ]);
    }
}
