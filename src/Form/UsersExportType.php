<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use AppBundle\Enum\Optin;

class UsersExportType extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('optins', ChoiceType::class, [
            'label'    => 'adminDashboard.users.export.optins.title',
            'translation_domain' => 'messages',
            'required' => true,
            'mapped'   => false,
            'expanded' => true,
            'multiple' => false,
            'choices'  => [
                array_map(function ($optin) {
                    return [
                        $optin->exportLabel() => $optin->getKey()
                    ];
                }, Optin::values()),
            ],
        ]);
    }
}
