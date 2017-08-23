<?php

namespace AppBundle\Form;

use AppBundle\Form\AddressType;
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

        $object = new Address($formData);

        $validator = $this->get('validator');

        $errors = $validator->validate($object);

        $this->assertEquals($errors[0]->getMessage(), "This value should not be blank.");

    }
}


?>
