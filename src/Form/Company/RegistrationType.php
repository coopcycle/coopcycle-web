<?php
declare(strict_types=1);

namespace AppBundle\Form\Company;

use AppBundle\Form\AddressType;
use AppBundle\Message\Company\RequestRegistration;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegistrationType extends AbstractType implements DataMapperInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('company_name', TextType::class,
                [
                    'label' => 'form.address.company_name.label',
                ])
            ->add('address', AddressType::class, [
                'label' => false,
                'with_widget' => true,
                'with_description' => false,
            ])
            ->add('business_referent', BusinessReferentType::class, [
                'label' => 'form.address.company.business_reference.label',
            ])
            ->add('collaborator_number', IntegerType::class, [
                'label' => 'form.address.company.collaborator_number.label',
            ])
            ->add('meal_estimate', IntegerType::class, [
                'label' => 'form.address.company.meal_estimate.label',
            ])
            ->setDataMapper($this)
        ;

    }

    public function mapDataToForms($viewData, $forms)
    {
        $forms = iterator_to_array($forms);





    }

    public function mapFormsToData($forms, &$viewData)
    {
        $forms = iterator_to_array($forms);
        $viewData = new RequestRegistration(
            $forms['company_name']->getData(),
            $forms['address']->getData(),
            $forms['business_referent']->getData(),
            (int)$forms['collaborator_number']->getData(),
            (int)$forms['meal_estimate']->getData(),
        );

    }

    public function configureOptions(OptionsResolver $resolver)
    {
    }
}
