<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class RfmThresholdsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        foreach ([
            'rfm_r_score4_max_days',
            'rfm_r_score3_max_days',
            'rfm_r_score2_max_days',
            'rfm_f_score4_min_orders',
            'rfm_f_score3_min_orders',
            'rfm_f_score2_min_orders',
        ] as $name) {
            $builder->add($name, IntegerType::class, [
                'label'       => 'rfm.thresholds.' . $name,
                'constraints' => [new Assert\Positive()],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'  => null,
            'constraints' => [new Assert\Callback([$this, 'validateOrder'])],
        ]);
    }

    public function validateOrder(array $data, ExecutionContextInterface $context): void
    {
        if (($data['rfm_r_score4_max_days'] ?? 0) >= ($data['rfm_r_score3_max_days'] ?? 0) ||
            ($data['rfm_r_score3_max_days'] ?? 0) >= ($data['rfm_r_score2_max_days'] ?? 0)) {
            $context->addViolation('rfm.thresholds.error.r_order');
        }

        if (($data['rfm_f_score2_min_orders'] ?? 0) >= ($data['rfm_f_score3_min_orders'] ?? 0) ||
            ($data['rfm_f_score3_min_orders'] ?? 0) >= ($data['rfm_f_score4_min_orders'] ?? 0)) {
            $context->addViolation('rfm.thresholds.error.f_order');
        }
    }
}
