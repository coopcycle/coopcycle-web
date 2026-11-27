<?php

namespace AppBundle\Form\GiftCard;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Form\Type\MoneyType;
use AppBundle\Service\GiftCard\GiftCardStatus;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a customer fills in to buy a gift card.
 *
 * The purchaser's email is asked for even when they are signed in, because
 * the receipt and the card go to two different places and the receipt is the
 * buyer's proof of purchase.
 */
class GiftCardPurchaseType extends AbstractType
{
    public function __construct(private readonly GiftCardStatus $giftCardStatus)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', MoneyType::class, [
                'label' => 'gift_card.purchase.field.amount',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Range(
                        min: $this->giftCardStatus->getMinAmount(),
                        max: $this->giftCardStatus->getMaxAmount(),
                        notInRangeMessage: 'gift_card.purchase.amount.out_of_range',
                    ),
                ],
            ])
            ->add('recipientName', TextType::class, [
                'label' => 'gift_card.purchase.field.recipientName',
                'required' => false,
            ])
            // Asked for twice, because this is the only address the code is
            // ever sent to and a typo hands a working gift card to a stranger
            // -- or to nobody. Assert\Email() can't catch that: "gmial.com"
            // is perfectly good syntax. Retyping it is the only check there
            // is short of verifying deliverability.
            //
            // Still optional: both boxes left empty means the buyer is
            // handing the card over themselves, and the code goes to them.
            // RepeatedType maps back to a single string, so everything
            // downstream still reads $data['recipientEmail'].
            ->add('recipientEmail', RepeatedType::class, [
                'type' => EmailType::class,
                'required' => false,
                'invalid_message' => 'gift_card.purchase.recipientEmail.mismatch',
                'first_options' => [
                    'label' => 'gift_card.purchase.field.recipientEmail',
                    'help' => 'gift_card.purchase.field.recipientEmail.help',
                ],
                'second_options' => [
                    'label' => 'gift_card.purchase.field.recipientEmailConfirm',
                ],
                'constraints' => [new Assert\Email()],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'gift_card.purchase.field.message',
                'required' => false,
                'attr' => ['rows' => 3, 'maxlength' => 500],
                'constraints' => [new Assert\Length(max: 500)],
            ])
            ->add('purchaserEmail', EmailType::class, [
                'label' => 'gift_card.purchase.field.purchaserEmail',
                'help' => 'gift_card.purchase.field.purchaserEmail.help',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Email(),
                ],
            ]);

        if ($this->giftCardStatus->isScopingEnabled()) {
            $builder->add('restaurant', EntityType::class, [
                'label' => 'gift_card.purchase.field.restaurant',
                'help' => 'gift_card.purchase.field.restaurant.help',
                'class' => LocalBusiness::class,
                'required' => false,
                'placeholder' => 'gift_card.purchase.field.restaurant.placeholder',
                'choice_label' => 'name',
                'query_builder' => fn (EntityRepository $repository) => $repository
                    ->createQueryBuilder('r')
                    ->andWhere('r.enabled = :enabled')
                    ->setParameter('enabled', true)
                    ->orderBy('r.name', 'ASC'),
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
