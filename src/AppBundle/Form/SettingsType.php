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
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
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
            ->add('default_tax_category', TaxCategoryChoiceType::class, [
                'label' => 'form.settings.default_tax_category.label'
            ]);

        foreach ($this->settingsManager->getSettings() as $name) {

            if ($builder->has($name)) {
                continue;
            }

            $type = TextType::class;
            $secret = $this->settingsManager->isSecret($name);
            $options = [
                'required' => true,
                'label' => sprintf('form.settings.%s.label', $name)
            ];

            if ($secret) {
                $type = PasswordType::class;
            }

            $value = $this->settingsManager->get($name);
            if ($secret && $value) {
                $options['empty_data'] = $value;
                $options['required'] = false;
                $options['attr'] = [
                    'placeholder' => $this->createPlaceholder($value)
                ];
            }

            $builder->add($name, $type, $options);
        }

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

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($builder) {

            $form = $event->getForm();

            $data = [];
            foreach ($this->settingsManager->getSettings() as $name) {
                $value = $this->settingsManager->get($name);
                if ($value) {
                    $data[$name] = $value;
                }
            }

            $event->setData($data);

            // Make sure there is an empty choice
            if (!isset($data['default_tax_category']) || !$data['default_tax_category']) {

                $defaultTaxCategory = $form->get('default_tax_category');
                $options = $defaultTaxCategory->getConfig()->getOptions();

                $options['placeholder'] = '';
                $options['required'] = false;

                $form->add('default_tax_category', TaxCategoryChoiceType::class, $options);
            }
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $defaultTaxCategory = null;
        foreach ($view as $name => $field) {
            if ($name === 'default_tax_category') {
                $defaultTaxCategory = $field;
                $view->offsetUnset($name);
            }
        }

        // Put default_tax_category at the end
        $view->children[] = $defaultTaxCategory;

        parent::finishView($view, $form, $options);
    }
}
