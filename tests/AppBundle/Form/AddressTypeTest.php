<?php

namespace Test\AppBundle\Form;

use AppBundle\Form\AddressType;
use AppBundle\Entity\Address;
use Symfony\Component\Form\Test\TypeTestCase;


class AddressTypeTest extends TypeTestCase {

    public function testInValidData () {
        $formData = array(
            'firstName'=> 'blabla'
        );

        $object = new Address($formData);

        $form = $this->factory->create(AddressType::class);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($object, $form->getData());
        $this->assertFalse($form->isValid());
    }
}


?>
