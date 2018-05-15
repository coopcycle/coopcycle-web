<?php

namespace AppBundle\Form;

use AppBundle\Entity\Menu;
use AppBundle\Form\MenuType\MenuSectionType;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityRepository;
use Sylius\Bundle\TaxationBundle\Form\Type\TaxCategoryChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SettingsType extends AbstractType
{
    private $settingsManager;

    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    private function createPlaceholder($value)
    {
        return implode('', array_pad([], strlen($value), '•'));
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('brand_name', TextType::class, [
                'label' => 'form.settings.brand_name.label'
            ])
            ->add('administrator_email', EmailType::class, [
                'label' => 'form.settings.administrator_email.label'
            ])
            ->add('stripe_test_publishable_key', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_publishable_key.label'
            ])
            ->add('stripe_test_secret_key', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_secret_key.label'
            ])
            ->add('stripe_test_connect_client_id', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_connect_client_id.label'
            ])
            ->add('stripe_live_publishable_key', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_publishable_key.label'
            ])
            ->add('stripe_live_secret_key', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_secret_key.label'
            ])
            ->add('stripe_live_connect_client_id', PasswordType::class, [
                'required' => false,
                'label' => 'form.settings.stripe_connect_client_id.label'
            ])
            ->add('stripe_livemode', ChoiceType::class, [
                'choices'  => [
                    'No' => 'no',
                    'Yes' => 'yes',
                ],
                'expanded' => true,
                'multiple' => false,
                'label' => 'form.settings.stripe_livemode.label'
            ])
            ->add('google_api_key', TextType::class, [
                'label' => 'form.settings.google_api_key.label'
            ])
            ->add('latlng', TextType::class, [
                'label' => 'form.settings.latlng.label'
            ])
            ->add('default_tax_category', TaxCategoryChoiceType::class, [
                'label' => 'form.settings.default_tax_category.label'
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            foreach ($data as $name => $value) {
                if ($this->settingsManager->isSecret($name)) {

                    $config = $form->get($name)->getConfig();
                    $options = $config->getOptions();

                    $options['empty_data'] = $value;
                    $options['required'] = false;
                    $options['attr'] = [
                        'placeholder' => $this->createPlaceholder($value)
                    ];

                    $form->add($name, PasswordType::class, $options);
                }
            }

            // Make sure there is an empty choice
            if (!$data->default_tax_category) {

                $defaultTaxCategory = $form->get('default_tax_category');
                $options = $defaultTaxCategory->getConfig()->getOptions();

                $options['placeholder'] = '';
                $options['required'] = false;

                $form->add('default_tax_category', TaxCategoryChoiceType::class, $options);
            }

        });

        $builder->get('default_tax_category')->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {

            $form = $event->getForm();
            $data = $event->getData();

            $options = $form->getConfig()->getOptions();
            foreach ($options['choices'] as $taxCategory) {
                if ($taxCategory->getCode() === $data) {
                    $form->setData($taxCategory);
                    break;
                }
            }

        });

        $builder->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) use ($builder) {
            $data = $event->getData();
            if (null !== $data->default_tax_category) {
                $data->default_tax_category = $data->default_tax_category->getCode();
            }
            $event->setData($data);
        });
    }
}
