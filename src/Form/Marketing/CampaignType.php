<?php

namespace AppBundle\Form\Marketing;

use AppBundle\Entity\Marketing\Campaign;
use AppBundle\Sylius\Promotion\PromotionCouponInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Sylius\Component\Promotion\Repository\PromotionCouponRepositoryInterface;

class CampaignType extends AbstractType
{
    public function __construct(
        private readonly PromotionCouponRepositoryInterface $promotionCouponRepository,
    ) {
    }

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
            // Typed rather than picked from a list. An instance that has run
            // coupon generation has tens of thousands of codes -- this one
            // has 37,000 -- and loading them all into a select makes the page
            // unusable. Nor is there an honest rule for which of them to
            // offer: usage limits don't tell a shareable code from one of a
            // single-use batch. The admin is pasting the code into the
            // message anyway, so they have it in hand.
            ->add('promotionCoupon', TextType::class, [
                'label' => 'marketing.campaign.field.promotionCoupon',
                'help' => 'marketing.campaign.field.promotionCoupon.help',
                'required' => false,
                'invalid_message' => 'marketing.campaign.field.promotionCoupon.unknown',
            ])
            ->add('scheduledAt', DateTimeType::class, [
                'label' => 'marketing.campaign.field.scheduledAt',
                'help' => 'marketing.campaign.field.scheduledAt.help',
                'required' => false,
                'widget' => 'single_text',
                'constraints' => [new Assert\GreaterThan('now', message: 'marketing.campaign.field.scheduledAt.in_the_past')],
            ]);

        $builder->get('promotionCoupon')->addModelTransformer(new CallbackTransformer(
            fn (?PromotionCouponInterface $coupon) => $coupon?->getCode(),
            fn (?string $code) => $this->findCoupon($code),
        ));
    }

    private function findCoupon(?string $code): ?PromotionCouponInterface
    {
        $code = trim((string) $code);

        if ('' === $code) {
            return null;
        }

        $coupon = $this->promotionCouponRepository->findOneBy(['code' => $code]);

        if (!$coupon instanceof PromotionCouponInterface) {
            throw new TransformationFailedException(sprintf('No coupon with code "%s"', $code));
        }

        // Coupons minted for one specific customer by the referral and
        // loyalty programs are redeemable only by them, so putting one in a
        // campaign would send a code that fails for everybody who got it.
        if ($coupon->isInternal()) {
            throw new TransformationFailedException(sprintf('Coupon "%s" belongs to one customer', $code));
        }

        return $coupon;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Campaign::class,
        ]);
    }
}
