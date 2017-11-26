<?php

namespace AppBundle\Form;

use AppBundle\Entity\ApiUser;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UpdateProfileType extends AbstractType
{
    private $countryIso;

    public function __construct($countryIso)
    {
        $this->countryIso = strtoupper($countryIso);
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('username', TextType::class)
                ->add('familyName', TextType::class, array('label' => 'Family name'))
                ->add('givenName', TextType::class, array('label' => 'Given name'))
                ->add('telephone', PhoneNumberType::class,
                    array('label' => 'Telephone',
                          'format' => PhoneNumberFormat::NATIONAL,
                          'default_region' => $this->countryIso));

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($options) {
                $user = $event->getData();
                if ($user->hasRole('ROLE_RESTAURANT') && $options['with_restaurants']) {
                    $restaurants = $user->getRestaurants();
                    $event->getForm()->add('restaurants', CollectionType::class, array(
                        'entry_type' => EntityType::class,
                        'entry_options' => array(
                            'label' => 'Restaurants',
                            'class' => 'AppBundle:Restaurant',
                            'choice_label' => 'name',
                        ),
                        'allow_add' => true,
                        'allow_delete' => true,
                    ));
                }
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
           'data_class' => ApiUser::class,
           'with_restaurants' => false
        ));
    }
}
