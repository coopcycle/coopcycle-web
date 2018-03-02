<?php

namespace AppBundle\Form;

use AppBundle\Entity\Menu;
use AppBundle\Form\MenuType\MenuSectionType;
use AppBundle\Service\SettingsManager;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
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
        foreach ($this->settingsManager->getSettings() as $name) {

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

        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
            $form = $event->getForm();
            foreach ($this->settingsManager->getSettings() as $name) {
                $value = $this->settingsManager->get($name);
                if ($value) {
                    $form->get($name)->setData($value);
                }
            }
        });
    }
}
