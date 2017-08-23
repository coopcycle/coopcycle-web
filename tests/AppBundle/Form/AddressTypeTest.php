<?php

namespace AppBundle\Form;

use AppBundle\Form\AddressType;
use Symfony\Component\Form\Forms;
use AppBundle\Entity\Address;
use Tests\AppBundle\ContainerAwareUnitTestCase;


class AddressTypeTest extends ContainerAwareUnitTestCase {

    public function testInValidData () {
        $formData = array(
            'name' => 'test',
            'streetAddress' => 'xxx',
            'postalCode' => '44300',
            'addressLocality' => 'Nantes',
            'phoneNumber' => '45652'
        );

        $factory = Forms::createFormFactoryBuilder()->getFormFactory();

        $form = $factory->create(AddressType::class);
        $form->submit($formData);

        $object = $form->getData();

        $validator = $this->get('validator');

        $errors = $validator->validate($object, null, array('delivery_address'));

        $this->assertEquals($errors[0]->getPropertyPath(), "familyName");
        $this->assertEquals($errors[0]->getMessage(), "This value should not be blank.");

    }
}


?>
