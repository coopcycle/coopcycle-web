<?php

namespace AppBundle\Form\Type;

use AppBundle\DataType\NumRange;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MinMaxType extends AbstractType implements DataMapperInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('lower', IntegerType::class, [
                'label' => 'form.min_max.lower.label',
            ])
            ->add('upper', TextType::class, [
                'label' => 'form.min_max.upper.label',
                'required' => false,
            ])
            ->add('infinity', CheckboxType::class, [
                'label' => 'form.min_max.infinity.label',
                'required' => false,
                'mapped' => false,
            ])
            ->setDataMapper($this);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => NumRange::class,
        ));
    }

    /**
     * @param NumRange|null $viewData
     */
    public function mapDataToForms($viewData, $forms)
    {
        $data = null;

        if (null === $viewData) {
            $data = new NumRange();
        } else {
            if (!$viewData instanceof NumRange) {
                throw new UnexpectedTypeException($viewData, NumRange::class);
            }
            $data = $viewData;
        }

        $forms = iterator_to_array($forms);

        $forms['lower']->setData($data->getLower());
        if ($data->isUpperInfinite()) {
            $forms['upper']->setData(NumRange::DEFAULT_UPPER);
        } else {
            $forms['upper']->setData($data->getUpper());
        }
        $forms['infinity']->setData($data->isUpperInfinite());
    }

    public function mapFormsToData($forms, &$viewData)
    {
        $forms = iterator_to_array($forms);

        $lower = $forms['lower']->getData();
        $upper = $forms['upper']->getData();
        $infinity = $forms['infinity']->getData();

        $numRange = new NumRange();
        $numRange->setLower($lower);
        $numRange->setUpper($infinity ? NumRange::UPPER_INF : $upper);

        $viewData = $numRange;
    }
}
