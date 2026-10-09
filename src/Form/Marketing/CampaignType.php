<?php

namespace AppBundle\Form\Marketing;

use AppBundle\Entity\Marketing\Campaign;
use AppBundle\Entity\Sylius\PromotionCoupon;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CampaignType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'marketing.campaign.field.name',
                'help' => 'marketing.campaign.field.name.help',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('subject', TextType::class, [
                'label' => 'marketing.campaign.field.subject',
                'help' => 'marketing.campaign.field.subject.help',
                'constraints' => [new Assert\NotBlank()],
            ])
            // Written by the editor, which reads and writes this field
            // directly; hidden rather than absent so the form still carries
            // the body on submit.
            ->add('bodyMjml', TextareaType::class, [
                'label' => false,
                'required' => false,
                'attr' => ['class' => 'd-none'],
            ])
            ->add('promotionCoupon', EntityType::class, [
                'label' => 'marketing.campaign.field.promotionCoupon',
                'help' => 'marketing.campaign.field.promotionCoupon.help',
                'required' => false,
                'placeholder' => 'marketing.campaign.field.promotionCoupon.none',
                'class' => PromotionCoupon::class,
                'choice_label' => 'code',
                'query_builder' => fn (EntityRepository $repository) => $repository
                    ->createQueryBuilder('c')
                    // Coupons minted for one specific customer by the
                    // referral and loyalty programs would be useless in a
                    // campaign -- nobody else can redeem them.
                    ->andWhere('c.internal = false')
                    ->orderBy('c.code', 'ASC'),
            ])
            ->add('scheduledAt', DateTimeType::class, [
                'label' => 'marketing.campaign.field.scheduledAt',
                'help' => 'marketing.campaign.field.scheduledAt.help',
                'required' => false,
                'widget' => 'single_text',
                'constraints' => [new Assert\GreaterThan('now', message: 'marketing.campaign.field.scheduledAt.in_the_past')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Campaign::class,
        ]);
    }
}
