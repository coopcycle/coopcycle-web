<?php

namespace AppBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;

class DeliveryAddressType extends AddressType {
    /*
     * Address form  to use on the order confirmation page
     */

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        // long story short : make streetAddress , postalCode and addressLocality disabled

        $streetAddressField = $builder->get('streetAddress');
        $fieldOptions = $streetAddressField->getOptions();
        $fieldType = get_class($streetAddressField->getType()->getInnerType());
        $fieldOptions['disabled'] = true;
        $builder->add($streetAddressField->getName(), $fieldType, $fieldOptions);

        $postalCodeField = $builder->get('postalCode');
        $fieldOptions = $postalCodeField->getOptions();
        $fieldType = get_class($postalCodeField->getType()->getInnerType());
        $fieldOptions['disabled'] = true;
        $builder->add($postalCodeField->getName(), $fieldType, $fieldOptions);

        $addressLocalityField = $builder->get('addressLocality');
        $fieldOptions = $addressLocalityField->getOptions();
        $fieldType = get_class($addressLocalityField->getType()->getInnerType());
        $fieldOptions['disabled'] = true;
        $builder->add($addressLocalityField->getName(), $fieldType, $fieldOptions);

    }

}

?>