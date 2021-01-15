<?php
declare(strict_types=1);

namespace AppBundle\Form\Restaurant;

use AppBundle\Form\AddressType;
use AppBundle\Message\Request\RequestRestaurant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class RequestForAddType extends AbstractType implements DataMapperInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'form.restaurant.name.label',
            ])
            ->add('address', AddressType::class, [
                'label' => false,
                'with_widget' => true,
                'with_description' => false,
            ])
            ->add('contact', EmailType::class, [
                    'label' => 'form.restaurant.contact_referent.label',
            ])
            ->add('b2b', CheckboxType::class, [
                'label' => 'form.restaurant.b2b.label',
                'required' => false,
            ])
            ->setDataMapper($this)
        ;
    }

    public function mapDataToForms($viewData, $forms)
    {

    }

    public function mapFormsToData($forms, &$viewData)
    {
        $forms = iterator_to_array($forms);
        $viewData = new RequestRestaurant(
            $forms['name']->getData(),
            $forms['address']->getData(),
            $forms['contact']->getData(),
            $forms['b2b']->getData()
        );
    }
}
