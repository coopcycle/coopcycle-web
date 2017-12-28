<?php

namespace AppBundle\Form;

use AppBundle\Entity\ApiUser;
use libphonenumber\PhoneNumberFormat;
use Misd\PhoneNumberBundle\Form\Type\PhoneNumberType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UpdateProfileType extends AbstractType
{
    private $tokenStorage;
    private $translator;
    private $countryIso;

    public function __construct(TokenStorageInterface $tokenStorage, TranslatorInterface $translator, $countryIso)
    {
        $this->tokenStorage = $tokenStorage;
        $this->translator = $translator;
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


        $isAdmin = false;
        if ($token = $this->tokenStorage->getToken()) {
            if ($user = $token->getUser()) {
                $isAdmin = $user->hasRole('ROLE_ADMIN');
            }
        }

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($options, $isAdmin) {

                $user = $event->getData();

                if ($isAdmin && $options['with_roles'] && !empty($options['editable_roles'])) {
                    $data = array_filter($user->getRoles(), function ($role) use ($options) {
                        return in_array($role, $options['editable_roles']);
                    });
                    $choices = array_combine($options['editable_roles'], $options['editable_roles']);
                    $event->getForm()->add('roles', ChoiceType::class, [
                        // Use mapped = false for security reasons
                        'mapped' => false,
                        'choices' => $choices,
                        'choice_label' => function ($value, $key, $index) {
                            return $this->translator->trans("roles.{$key}.help");
                        },
                        'expanded' => true,
                        'multiple' => true,
                        'data' => $data
                    ]);
                }

                if ($user->hasRole('ROLE_RESTAURANT') && $options['with_restaurants']) {
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

                if ($user->hasRole('ROLE_STORE') && $options['with_stores']) {
                    $event->getForm()->add('stores', CollectionType::class, array(
                        'entry_type' => EntityType::class,
                        'entry_options' => array(
                            'label' => 'Stores',
                            'class' => 'AppBundle:Store',
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
           'with_restaurants' => false,
           'with_stores' => false,
           'with_roles' => false,
           'editable_roles' => []
        ));
    }
}
