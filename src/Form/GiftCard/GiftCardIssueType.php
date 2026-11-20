<?php

namespace AppBundle\Form\GiftCard;

use AppBundle\Entity\LocalBusiness;
use AppBundle\Form\Type\MoneyType;
use AppBundle\Service\GiftCard\GiftCardStatus;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Issuing a card by hand: compensation for an order that went wrong, a
 * marketing giveaway, a card sold over the counter.
 *
 * A card issued here is *granted*, not funded -- there is no Stripe charge
 * behind it, so the coop is paying for whatever it buys out of its own
 * pocket. That is a legitimate thing to do and the ledger records it as
 * such, but it is also real money leaving, so the screen says so rather than
 * making it look like any other card.
 */
class GiftCardIssueType extends AbstractType
{
    public function __construct(private readonly GiftCardStatus $giftCardStatus)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', MoneyType::class, [
                'label' => 'gift_card.issue.field.amount',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Positive(),
                ],
            ])
            ->add('recipientEmail', EmailType::class, [
                'label' => 'gift_card.issue.field.recipientEmail',
                'help' => 'gift_card.issue.field.recipientEmail.help',
                'required' => false,
                'constraints' => [new Assert\Email()],
            ])
            ->add('recipientName', TextType::class, [
                'label' => 'gift_card.issue.field.recipientName',
                'required' => false,
            ])
            ->add('message', TextareaType::class, [
                'label' => 'gift_card.issue.field.message',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            // Not a note for the customer: this is why the coop handed money
            // out, and it is the only thing that will make sense of the
            // ledger row a year from now.
            ->add('reason', TextType::class, [
                'label' => 'gift_card.issue.field.reason',
                'help' => 'gift_card.issue.field.reason.help',
                'required' => false,
            ]);

        if ($this->giftCardStatus->isScopingEnabled()) {
            $builder->add('restaurant', EntityType::class, [
                'label' => 'gift_card.issue.field.restaurant',
                'help' => 'gift_card.issue.field.restaurant.help',
                'class' => LocalBusiness::class,
                'required' => false,
                'placeholder' => 'gift_card.issue.field.restaurant.placeholder',
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
